import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

/**
 * A single-choice segmented control (scope, format, layout in the export dialog).
 */
export function Segmented<T extends string>({
    value,
    onChange,
    options,
    label,
}: {
    value: T;
    onChange: (value: T) => void;
    options: { value: T; label: string; disabled?: boolean }[];
    label: string;
}) {
    return (
        <ToggleGroup
            type="single"
            variant="outline"
            value={value}
            onValueChange={(next) => next && onChange(next as T)}
            aria-label={label}
            className="w-full flex-wrap"
        >
            {options.map((option) => (
                <ToggleGroupItem
                    key={option.value}
                    value={option.value}
                    disabled={option.disabled}
                    className="flex-1 px-3 data-[state=on]:bg-primary data-[state=on]:text-primary-foreground"
                >
                    {option.label}
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}
