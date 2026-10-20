import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import ExportController from '@/actions/App/Http/Controllers/ExportController';
import type { ExportFile, ExportSelection } from '@/types/catalogix';

type State = 'idle' | 'preparing';

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function request(
    url: string,
    method: 'GET' | 'POST',
    selection?: ExportSelection,
): Promise<ExportFile> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
            'Content-Type': 'application/json',
        },
        ...(method === 'POST' ? { body: JSON.stringify(selection ?? {}) } : {}),
    });

    if (!response.ok) {
        if (response.status === 422) {
            const error = (await response.json()) as { message?: string };
            throw new Error(
                error.message ?? 'Please check the selected products.',
            );
        }
        throw new Error(
            response.status === 429
                ? 'Too many downloads at once — try again in a minute.'
                : 'The Excel file could not be started.',
        );
    }

    return ((await response.json()) as { data: ExportFile }).data;
}

/**
 * One click → Excel with pictures for a job: starts the export, waits for it and downloads the
 * file as soon as it is ready. No dialog and no trip to the Exports page.
 */
export function useExcelDownload(): {
    download: (jobId: number, selection?: ExportSelection) => void;
    preparing: (jobId: number) => boolean;
} {
    const [states, setStates] = useState<Record<number, State>>({});
    const timers = useRef<ReturnType<typeof setTimeout>[]>([]);

    useEffect(() => () => timers.current.forEach(clearTimeout), []);

    const finish = (jobId: number) =>
        setStates((current) => ({ ...current, [jobId]: 'idle' }));

    const download = useCallback(
        (jobId: number, selection?: ExportSelection) => {
            setStates((current) => ({ ...current, [jobId]: 'preparing' }));
            const toastId = toast.loading(
                'Preparing your Excel file with pictures…',
            );
            const started = Date.now();

            const poll = (exportId: number) => {
                timers.current.push(
                    setTimeout(async () => {
                        try {
                            const file = await request(
                                ExportController.status.url(exportId),
                                'GET',
                            );

                            if (file.status === 'ready' && file.download_url) {
                                toast.success(
                                    'Your Excel file is downloading.',
                                    {
                                        id: toastId,
                                    },
                                );
                                window.location.assign(file.download_url);
                                finish(jobId);
                            } else if (file.status === 'failed') {
                                toast.error(
                                    file.error ??
                                        'The Excel file could not be made.',
                                    { id: toastId },
                                );
                                finish(jobId);
                            } else if (Date.now() - started > 10 * 60 * 1000) {
                                toast.info(
                                    'Still working on it — the file will be under Downloads when ready.',
                                    { id: toastId },
                                );
                                finish(jobId);
                            } else {
                                poll(exportId);
                            }
                        } catch (error) {
                            toast.error((error as Error).message, {
                                id: toastId,
                            });
                            finish(jobId);
                        }
                    }, 1500),
                );
            };

            request(ExportController.quick.url(jobId), 'POST', selection)
                .then((file) => poll(file.id))
                .catch((error: Error) => {
                    toast.error(error.message, { id: toastId });
                    finish(jobId);
                });
        },
        [],
    );

    return {
        download,
        preparing: (jobId: number) => states[jobId] === 'preparing',
    };
}
