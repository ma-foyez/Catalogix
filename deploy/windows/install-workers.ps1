param(
    [string]$NssmPath = 'nssm',
    [string]$PhpPath = (Get-Command php -ErrorAction Stop).Source,
    [string]$ServiceName = 'CatalogixWorkers'
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\..')).Path
$artisanPath = Join-Path $projectRoot 'artisan'
$phpExecutable = (Get-Command $PhpPath -ErrorAction Stop).Source
$nssmExecutable = (Get-Command $NssmPath -ErrorAction Stop).Source
$logDirectory = Join-Path $projectRoot 'storage\logs'

if (Get-Service -Name $ServiceName -ErrorAction SilentlyContinue) {
    throw "Service '$ServiceName' already exists. Update or restart that service instead."
}

# Run this installer once from an Administrator PowerShell, with NSSM installed.
# NSSM runs the worker pool without a visible terminal and starts it after Windows boots.
& $nssmExecutable install $ServiceName $phpExecutable
if ($LASTEXITCODE -ne 0) { throw 'Could not install the worker service.' }

$serviceParameters = '"' + $artisanPath + '" catalogix:work'
$settings = @{
    AppDirectory = $projectRoot
    AppParameters = $serviceParameters
    DisplayName = 'Catalogix background workers'
    Description = 'Automatic discovery, product extraction, image downloads and exports.'
    Start = 'SERVICE_AUTO_START'
    AppStdout = (Join-Path $logDirectory 'workers-service.log')
    AppStderr = (Join-Path $logDirectory 'workers-service-error.log')
    AppExit = @('Default', 'Restart')
    AppRestartDelay = '5000'
    AppRotateFiles = '1'
    AppRotateOnline = '1'
    AppRotateBytes = '10485760'
    AppKillProcessTree = '1'
    AppStopMethodConsole = '970000'
}

foreach ($setting in $settings.GetEnumerator()) {
    & $nssmExecutable set $ServiceName $setting.Key @($setting.Value)
    if ($LASTEXITCODE -ne 0) { throw "Could not configure $($setting.Key)." }
}

& $nssmExecutable start $ServiceName
if ($LASTEXITCODE -ne 0) { throw 'Could not start the worker service. Check storage\logs\workers-service-error.log.' }
Write-Host "Installed and started $ServiceName. Workers now start automatically after reboot."
