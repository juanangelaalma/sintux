import Button from '@/components/ui/button';
import { cn } from '@/lib/utils';

type FormActionsProps = {
    onCancel: () => void;
    submitLabel?: string;
    cancelLabel?: string;
    processing?: boolean;
    danger?: boolean;
    className?: string;
    submitDataTest?: string;
};

export default function FormActions({
    onCancel,
    submitLabel = 'Save',
    cancelLabel = 'Cancel',
    processing = false,
    danger = false,
    className,
    submitDataTest,
}: FormActionsProps) {
    return (
        <div className={cn('flex justify-end gap-2 pt-2', className)}>
            <Button variant="secondary" onClick={onCancel}>
                {cancelLabel}
            </Button>
            <Button
                type="submit"
                variant={danger ? 'danger' : 'primary'}
                disabled={processing}
                data-test={submitDataTest}
            >
                {submitLabel}
            </Button>
        </div>
    );
}
