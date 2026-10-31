import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

/**
 * Add/Edit dialog shell shared by every settings tab.
 */
export function SettingsDialog({
    title,
    description,
    onClose,
    onSubmit,
    processing,
    submitLabel = 'Save',
    children,
    wide = false,
}: {
    title: string;
    description?: ReactNode;
    onClose: () => void;
    onSubmit: () => void;
    processing: boolean;
    submitLabel?: string;
    children: ReactNode;
    wide?: boolean;
}) {
    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent
                className={
                    wide
                        ? 'max-h-[95vh] overflow-y-auto sm:max-w-2xl'
                        : 'max-h-[95vh] overflow-y-auto'
                }
            >
                <form
                    className="space-y-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        onSubmit();
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        {description && (
                            <DialogDescription>{description}</DialogDescription>
                        )}
                    </DialogHeader>
                    {children}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** "Active" / "Off" in words (never colour alone). */
export function ActiveText({
    active,
    on = 'Active',
    off = 'Off',
}: {
    active: boolean;
    on?: string;
    off?: string;
}) {
    return (
        <span className={active ? 'font-medium' : 'text-muted-foreground'}>
            {active ? on : off}
        </span>
    );
}
