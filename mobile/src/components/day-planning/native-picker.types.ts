import type { PickerMode } from '@/day-planning/picker-values';

export type NativePickerProps = {
  value: Date;
  mode: PickerMode;
  minimumDate?: Date;
  onValueChange: (value: Date) => void;
  onDismiss: () => void;
};
