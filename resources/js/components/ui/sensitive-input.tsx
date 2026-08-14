import { forwardRef } from 'react';
import type { InputHTMLAttributes } from 'react';
import TextInput from './text-input';

export type SensitiveInputProps = InputHTMLAttributes<HTMLInputElement>;

/**
 * UI Component for PII / sensitive data fields (NIK, NPWP, Bank Accounts).
 * Masked by default using password type with interactive show/hide toggle.
 */
const SensitiveInput = forwardRef<HTMLInputElement, SensitiveInputProps>(
    (props, ref) => {
        return <TextInput ref={ref} type="password" autoComplete="off" {...props} />;
    },
);

SensitiveInput.displayName = 'SensitiveInput';

export default SensitiveInput;
