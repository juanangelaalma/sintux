import {
    Calendar,
    DateField,
    DatePicker,
    Label,
} from '@heroui/react';
import { parseDate } from '@internationalized/date';

type FormDatePickerProps = {
    label: string;
    value: string;
    onChange: (val: string) => void;
    isRequired?: boolean;
};

/**
 * Shared date picker with the standard form field look:
 * label on top, bordered input group, calendar popover trigger.
 */
export default function FormDatePicker({
    label,
    value,
    onChange,
    isRequired = true,
}: FormDatePickerProps) {
    return (
        <DatePicker
            className="w-full"
            isRequired={isRequired}
            value={value ? parseDate(value) : null}
            onChange={(date) => onChange(date ? date.toString() : '')}
        >
            <Label className="mb-1 block text-xs font-semibold text-foreground">
                {label} {isRequired && <span className="text-danger">*</span>}
            </Label>
            <DateField.Group
                fullWidth
                className="rounded-lg border border-border bg-surface px-2.5 py-2 focus-within:border-accent"
            >
                <DateField.Input>
                    {(segment) => <DateField.Segment segment={segment} />}
                </DateField.Input>
                <DateField.Suffix>
                    <DatePicker.Trigger className="text-muted hover:text-foreground">
                        <DatePicker.TriggerIndicator />
                    </DatePicker.Trigger>
                </DateField.Suffix>
            </DateField.Group>
            <DatePicker.Popover>
                <Calendar aria-label={label}>
                    <Calendar.Header>
                        <Calendar.Heading />
                        <Calendar.NavButton slot="previous" />
                        <Calendar.NavButton slot="next" />
                    </Calendar.Header>
                    <Calendar.Grid>
                        <Calendar.GridHeader>
                            {(day) => (
                                <Calendar.HeaderCell>{day}</Calendar.HeaderCell>
                            )}
                        </Calendar.GridHeader>
                        <Calendar.GridBody>
                            {(date) => <Calendar.Cell date={date} />}
                        </Calendar.GridBody>
                    </Calendar.Grid>
                </Calendar>
            </DatePicker.Popover>
        </DatePicker>
    );
}
