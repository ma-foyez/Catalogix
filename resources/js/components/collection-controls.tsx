import { router } from '@inertiajs/react';
import { Pause, Play } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

export function CollectionControls({
    id,
    paused,
    kind = 'discoveries',
}: {
    id: number;
    paused: boolean;
    kind?: 'discoveries' | 'jobs';
}) {
    const [busy, setBusy] = useState(false);

    return (
        <Button
            variant="outline"
            size="sm"
            disabled={busy}
            onClick={() => {
                setBusy(true);
                router.post(
                    `/${kind}/${id}/${paused ? 'resume' : 'pause'}`,
                    {},
                    {
                        preserveScroll: true,
                        onFinish: () => setBusy(false),
                    },
                );
            }}
        >
            {paused ? <Play /> : <Pause />}
            {paused ? 'Resume' : 'Pause'}
        </Button>
    );
}
