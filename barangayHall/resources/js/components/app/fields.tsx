import type { ComponentProps, ReactNode } from 'react';
import { DatePicker } from '@/components/app/date-picker';
import { describedBy, FormField } from '@/components/app/form-field';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

/*
 * Controlled form fields for useForm(). Each one wires the label, hint
 * and Laravel error to the input (aria-describedby / aria-invalid).
 */

type Base = {
    id: string;
    label: ReactNode;
    error?: string;
    required?: boolean;
    description?: ReactNode;
    className?: string;
};

export function TextField({
    id,
    label,
    error,
    required,
    description,
    className,
    value,
    onChange,
    ...props
}: Base & {
    value: string | number | null | undefined;
    onChange: (value: string) => void;
} & Omit<ComponentProps<typeof Input>, 'value' | 'onChange' | 'id'>) {
    return (
        <FormField
            id={id}
            label={label}
            error={error}
            required={required}
            description={description}
            className={className}
        >
            <Input
                id={id}
                value={value ?? ''}
                onChange={(e) => onChange(e.target.value)}
                required={required}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy(id, { description, error })}
                className="h-11"
                {...props}
            />
        </FormField>
    );
}

export function TextareaField({
    id,
    label,
    error,
    required,
    description,
    className,
    value,
    onChange,
    ...props
}: Base & {
    value: string | null | undefined;
    onChange: (value: string) => void;
} & Omit<ComponentProps<typeof Textarea>, 'value' | 'onChange' | 'id'>) {
    return (
        <FormField
            id={id}
            label={label}
            error={error}
            required={required}
            description={description}
            className={className}
        >
            <Textarea
                id={id}
                value={value ?? ''}
                onChange={(e) => onChange(e.target.value)}
                required={required}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy(id, { description, error })}
                {...props}
            />
        </FormField>
    );
}

export type SelectOption = { value: string | number; label: string };

export function SelectField({
    id,
    label,
    error,
    required,
    description,
    className,
    value,
    onChange,
    options,
    placeholder = 'Select…',
    disabled,
}: Base & {
    value: string | number | null | undefined;
    onChange: (value: string) => void;
    options: SelectOption[];
    placeholder?: string;
    disabled?: boolean;
}) {
    return (
        <FormField
            id={id}
            label={label}
            error={error}
            required={required}
            description={description}
            className={className}
        >
            <Select
                value={
                    value === null || value === undefined ? '' : String(value)
                }
                onValueChange={onChange}
                disabled={disabled}
            >
                <SelectTrigger
                    id={id}
                    className="w-full data-[size=default]:h-11"
                    aria-invalid={error ? true : undefined}
                    aria-describedby={describedBy(id, { description, error })}
                >
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem
                            key={option.value}
                            value={String(option.value)}
                        >
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </FormField>
    );
}

export function RadioField({
    id,
    label,
    error,
    required,
    description,
    className,
    value,
    onChange,
    options,
}: Base & {
    value: string | null | undefined;
    onChange: (value: string) => void;
    options: SelectOption[];
}) {
    return (
        <FormField
            id={id}
            label={label}
            error={error}
            required={required}
            description={description}
            className={className}
        >
            <RadioGroup
                id={id}
                value={value ?? ''}
                onValueChange={onChange}
                className="flex min-h-11 flex-wrap items-center gap-x-6 gap-y-2"
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy(id, { description, error })}
            >
                {options.map((option) => (
                    <div key={option.value} className="flex items-center gap-2">
                        <RadioGroupItem
                            value={String(option.value)}
                            id={`${id}-${option.value}`}
                            className="size-5"
                        />
                        <Label
                            htmlFor={`${id}-${option.value}`}
                            className="text-base font-normal"
                        >
                            {option.label}
                        </Label>
                    </div>
                ))}
            </RadioGroup>
        </FormField>
    );
}

export function DateField({
    id,
    label,
    error,
    required,
    description,
    className,
    value,
    onChange,
    disableFuture = true,
    fromYear,
}: Base & {
    value: string | null | undefined;
    onChange: (value: string | null) => void;
    disableFuture?: boolean;
    fromYear?: number;
}) {
    return (
        <FormField
            id={id}
            label={label}
            error={error}
            required={required}
            description={description}
            className={className}
        >
            <DatePicker
                id={id}
                value={value ?? null}
                onChange={onChange}
                disableFuture={disableFuture}
                fromYear={fromYear}
                invalid={!!error}
                describedBy={describedBy(id, { description, error })}
            />
        </FormField>
    );
}

export function CheckboxField({
    id,
    label,
    checked,
    onChange,
    description,
    error,
    className,
}: {
    id: string;
    label: ReactNode;
    checked: boolean;
    onChange: (checked: boolean) => void;
    description?: ReactNode;
    error?: string;
    className?: string;
}) {
    return (
        <div className={cn('flex items-start gap-3', className)}>
            <Checkbox
                id={id}
                checked={checked}
                onCheckedChange={(v) => onChange(v === true)}
                className="mt-0.5 size-5"
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy(id, { description, error })}
            />
            <div className="grid gap-1">
                <Label
                    htmlFor={id}
                    className="text-base leading-snug font-normal"
                >
                    {label}
                </Label>
                {description && (
                    <p
                        id={`${id}-description`}
                        className="text-sm text-muted-foreground"
                    >
                        {description}
                    </p>
                )}
                {error && (
                    <p
                        id={`${id}-error`}
                        role="alert"
                        className="text-sm text-destructive"
                    >
                        {error}
                    </p>
                )}
            </div>
        </div>
    );
}
