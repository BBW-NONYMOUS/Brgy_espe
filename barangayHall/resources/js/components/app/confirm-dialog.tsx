import type { ReactNode } from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { buttonVariants } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description: ReactNode;
    confirmLabel: string;
    cancelLabel?: string;
    /** Red confirm button for archive / delete / reset. */
    destructive?: boolean;
    onConfirm: () => void;
    processing?: boolean;
    /** Disable confirm until extra fields (e.g. a reason) are valid. */
    confirmDisabled?: boolean;
    /** Extra fields shown between the text and the buttons. */
    children?: ReactNode;
    /** Uncontrolled: the element that opens the dialog. */
    trigger?: ReactNode;
    /** Controlled usage (e.g. opened from a row DropdownMenu). */
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
};

/**
 * Confirmation before archiving, deleting, resetting a password, etc.
 * Built on AlertDialog: focus is trapped, Esc cancels, and the safe
 * choice (Cancel) receives focus first.
 */
export function ConfirmDialog({
    title,
    description,
    confirmLabel,
    cancelLabel = 'Cancel',
    destructive = false,
    onConfirm,
    processing = false,
    confirmDisabled = false,
    children,
    trigger,
    open,
    onOpenChange,
}: Props) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            {trigger && (
                <AlertDialogTrigger asChild>{trigger}</AlertDialogTrigger>
            )}
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription asChild>
                        <div className="space-y-2">{description}</div>
                    </AlertDialogDescription>
                </AlertDialogHeader>

                {children}

                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>
                        {cancelLabel}
                    </AlertDialogCancel>
                    <AlertDialogAction
                        className={cn(
                            destructive &&
                                buttonVariants({ variant: 'destructive' }),
                        )}
                        disabled={processing || confirmDisabled}
                        onClick={(event) => {
                            // Keep the dialog open until the request finishes;
                            // the caller closes it in onSuccess.
                            event.preventDefault();
                            onConfirm();
                        }}
                    >
                        {processing && <Spinner />}
                        {confirmLabel}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
