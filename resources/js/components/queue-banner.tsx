import { usePage } from '@inertiajs/react';
import { Check, Copy, PlugZap } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { useLivePoll } from '@/hooks/use-live-poll';

/**
 * Shown above every app page while work is waiting and no queue worker is listening —
 * otherwise a discovery just sits at "Queued" with no explanation.
 */
export function QueueBanner() {
    const { queueStalled: stall } = usePage().props;
    const [copied, copy] = useClipboard();

    // Keep checking so the banner disappears as soon as a worker starts.
    useLivePoll(stall !== null, [], 4000);

    if (!stall) {
        return null;
    }

    return (
        <div
            role="status"
            className="border-b border-warning/30 bg-warning/10 px-4 py-3 md:px-6"
        >
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div className="flex min-w-0 flex-1 items-start gap-3">
                    <span className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-warning/15 text-warning">
                        <PlugZap className="size-4" />
                    </span>
                    <div className="min-w-0 space-y-0.5">
                        <p className="text-sm font-medium">
                            No background worker is running — {stall.waiting}{' '}
                            {stall.waiting === 1 ? 'task is' : 'tasks are'}{' '}
                            waiting ({stall.queues.join(', ')})
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Discovery, extraction, image downloads and exports
                            run in a queue worker. Start one in a terminal (or
                            run <code className="font-mono">composer dev</code>,
                            which also starts Reverb and Vite); waiting work
                            starts right away.
                        </p>
                    </div>
                </div>
                <div className="flex min-w-0 items-center gap-2 lg:max-w-[45%]">
                    <code className="block min-w-0 flex-1 truncate rounded-md border bg-background px-2.5 py-1.5 font-mono text-xs">
                        {stall.command}
                    </code>
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => copy(stall.command)}
                        aria-label="Copy worker command"
                    >
                        {copied === stall.command ? <Check /> : <Copy />}
                        {copied === stall.command ? 'Copied' : 'Copy'}
                    </Button>
                </div>
            </div>
        </div>
    );
}
