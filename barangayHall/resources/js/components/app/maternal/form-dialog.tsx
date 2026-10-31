import type { FormEvent, ReactNode } from 'react';
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

export type DialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Dialog with a form, Cancel and a submit button that shows a spinner while
 * saving. Shared by the surveillance, maternal and child-health screens.
 */
export function FormDialog({
    open,
    onOpenChange,
    title,
    description,
    onSubmit,
    processing,
    submitLabel,
    destructive = false,
    children,
    className,
}: DialogProps & {
    title: string;
    description?: ReactNode;
    onSubmit: () => void;
    processing: boolean;
    submitLabel: string;
    destructive?: boolean;
    children: ReactNode;
    className?: string;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className={className ?? 'max-h-[90dvh] overflow-y-auto'}
            >
                <form
                    onSubmit={(e: FormEvent) => {
                        e.preventDefault();
                        onSubmit();
                    }}
                    className="space-y-5"
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
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant={destructive ? 'destructive' : 'default'}
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
