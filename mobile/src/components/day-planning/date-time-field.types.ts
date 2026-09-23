import type { PickerMode } from '@/day-planning/picker-values';

export type PlanningDateTimeFieldProps = {
  label: string;
  value: string;
  mode: PickerMode;
  onChange: (value: string) => void;
  disabled?: boolean;
  minimumDate?: string;
  title?: string;
};
