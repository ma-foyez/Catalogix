import type { Auth } from '@/types/auth';
import type { QueueStall } from '@/types/catalogix';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            queueStalled: QueueStall | null;
            [key: string]: unknown;
        };
    }
}
