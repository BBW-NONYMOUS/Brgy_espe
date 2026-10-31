import { Eye, EyeOff, Lock } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type Props = {
    /** Full value — only present when the server allowed it. */
    value?: string | null;
    masked: string | null;
    label: string;
};

/**
 * Shows a masked value (e.g. PhilHealth no.) with a "show" toggle when the
 * full value was sent, or a lock with an explanation when it was not.
 * Masked by default so it is not exposed to people looking at the screen.
 */
export function SensitiveValue({ value, masked, label }: Props) {
    const [revealed, setRevealed] = useState(false);

    if (!masked) {
        return <span className="text-muted-foreground">Not recorded</span>;
    }

    if (!value) {
        return (
            <span className="inline-flex items-center gap-1.5 font-mono">
                {masked}
                <Tooltip>
                    <TooltipTrigger asChild>
                        <span
                            tabIndex={0}
                            className="rounded text-muted-foreground"
                        >
                            <Lock
                                className="size-4"
                                aria-label="Hidden for your role"
                            />
                        </span>
                    </TooltipTrigger>
                    <TooltipContent>
                        Full {label} is hidden for your role.
                    </TooltipContent>
                </Tooltip>
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1.5">
            <span className="font-mono">{revealed ? value : masked}</span>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-8"
                onClick={() => setRevealed((v) => !v)}
                aria-label={revealed ? `Hide ${label}` : `Show ${label}`}
                aria-pressed={revealed}
            >
                {revealed ? <EyeOff aria-hidden /> : <Eye aria-hidden />}
            </Button>
        </span>
    );
}
