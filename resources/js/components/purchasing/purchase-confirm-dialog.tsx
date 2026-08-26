import { AlertDialog, Button } from '@heroui/react';

type PurchaseConfirmDialogProps = {
    open: boolean;
    title: string;
    description: string;
    confirmLabel: string;
    variant?: 'primary' | 'danger';
    processing?: boolean;
    onConfirm: () => void;
    onClose: () => void;
};

export default function PurchaseConfirmDialog({
    open,
    title,
    description,
    confirmLabel,
    variant = 'primary',
    processing = false,
    onConfirm,
    onClose,
}: PurchaseConfirmDialogProps) {
    return (
        <AlertDialog.Backdrop
            isOpen={open}
            onOpenChange={(next) => {
                if (!next) {
                    onClose();
                }
            }}
        >
            <AlertDialog.Container>
                <AlertDialog.Dialog className="sm:max-w-[400px]">
                    <AlertDialog.CloseTrigger />
                    <AlertDialog.Header>
                        <AlertDialog.Icon
                            status={variant === 'danger' ? 'danger' : 'accent'}
                        />
                        <AlertDialog.Heading>{title}</AlertDialog.Heading>
                    </AlertDialog.Header>
                    <AlertDialog.Body>
                        <p>{description}</p>
                    </AlertDialog.Body>
                    <AlertDialog.Footer>
                        <Button slot="close" variant="tertiary">
                            Batal
                        </Button>
                        <Button
                            isDisabled={processing}
                            variant={variant}
                            onPress={onConfirm}
                        >
                            {confirmLabel}
                        </Button>
                    </AlertDialog.Footer>
                </AlertDialog.Dialog>
            </AlertDialog.Container>
        </AlertDialog.Backdrop>
    );
}
