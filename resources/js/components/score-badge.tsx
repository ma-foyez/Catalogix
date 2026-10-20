import { ToneBadge } from '@/components/status-badge';
import type { ScoreLabel } from '@/types/catalogix';

const TONES = {
    high: 'success',
    medium: 'info',
    low: 'warning',
    none: 'neutral',
} as const;

const LABELS: Record<ScoreLabel, string> = {
    high: 'High',
    medium: 'Medium',
    low: 'Low',
    none: 'None',
};

export function ScoreBadge({
    label,
    score,
}: {
    label: ScoreLabel;
    score: number;
}) {
    return (
        <ToneBadge tone={TONES[label]}>
            {LABELS[label]}
            <span className="font-mono tabular-nums opacity-70">{score}</span>
        </ToneBadge>
    );
}
