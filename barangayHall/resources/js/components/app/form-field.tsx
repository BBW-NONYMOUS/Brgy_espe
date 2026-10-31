import type { ReactNode } from 'react';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import { cn } from '@/lib/utils';

type Props = {
    /** id of the input — links the label, hint and error to it. */
    id: string;
    label: ReactNode;
    required?: boolean;
    /** Plain-language hint shown under the label. */
    description?: ReactNode;
    /** Laravel validation message for this field. */
    error?: string;
    children: ReactNode;
    className?: string;
    orientation?: 'vertical' | 'horizontal';
};

/**
 * Label + input + hint + error, using shadcn Field styling.
 * Pass `aria-invalid={!!error}` and `aria-describedby={describedBy(id, …)}`
 * on the input so screen readers announce the hint and the error.
 */
export function FormField({
    id,
    label,
    required,
    description,
    error,
    children,
    className,
    orientation = 'vertical',
}: Props) {
    return (
        <Field
            data-invalid={error ? true : undefined}
            orientation={orientation}
            className={cn(className)}
        >
            <FieldLabel htmlFor={id}>
                {label}
                {required && (
                    <span className="text-destructive" aria-hidden>
                        *
                    </span>
                )}
                {required && <span className="sr-only">(required)</span>}
            </FieldLabel>
            {children}
            {description && (
                <FieldDescription id={`${id}-description`}>
                    {description}
                </FieldDescription>
            )}
            {error && (
                <FieldError id={`${id}-error`} role="alert">
                    {error}
                </FieldError>
            )}
        </Field>
    );
}

/** aria-describedby value for an input inside <FormField>. */
export function describedBy(
    id: string,
    { description, error }: { description?: unknown; error?: unknown },
): string | undefined {
    const ids = [
        description ? `${id}-description` : null,
        error ? `${id}-error` : null,
    ].filter(Boolean);

    return ids.length ? ids.join(' ') : undefined;
}
