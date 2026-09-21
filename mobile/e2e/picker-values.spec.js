const { test, expect } = require('@playwright/test');
const { pickerDate, pickerValue, pickerMinimumDate, formatPickerValue, isPickerValue } = require('../src/day-planning/picker-values');

const zones = ['UTC', 'Europe/Warsaw', 'America/Los_Angeles', 'Pacific/Kiritimati', 'Pacific/Pago_Pago', 'Asia/Kathmandu', 'Australia/Lord_Howe'];
const days = ['2026-01-01', '2026-03-29', '2026-10-25', '2026-12-31', '2028-02-29'];
const times = ['00:07', '02:37', '09:05', '23:59'];

function inDeviceZones(run) {
  const previous = process.env.TZ;
  try {
    for (const zone of zones) {
      process.env.TZ = zone;
      run(zone);
    }
  } finally {
    if (previous === undefined) delete process.env.TZ;
    else process.env.TZ = previous;
  }
}

test('Android calendar uses the requested UTC day while date limits use the device-local day', () => {
  inDeviceZones((zone) => {
    for (const day of days) {
      const initial = pickerDate(day, 'date', 'android');
      const nativeResult = new Date(`${day}T00:00:00Z`);
      expect(initial.toISOString().slice(0, 10), zone).toBe(day);
      expect(pickerValue(nativeResult, 'date', 'android'), zone).toBe(day);
      const limit = pickerMinimumDate(day, 'android');
      const parts = [limit.getFullYear(), limit.getMonth() + 1, limit.getDate()];
      expect(parts, zone).toEqual(day.split('-').map(Number));
    }
  });
});

test('Android clock keeps exact wall-clock minutes regardless of the device timezone', () => {
  inDeviceZones((zone) => {
    for (const time of times) {
      const initial = pickerDate(time, 'time', 'android');
      expect([initial.getHours(), initial.getMinutes()], zone).toEqual(time.split(':').map(Number));
      const nativeResult = new Date(initial);
      nativeResult.setHours(2, 37, 0, 0);
      expect(pickerValue(nativeResult, 'time', 'android'), zone).toBe('02:37');
    }
  });
});

test('SwiftUI calendar and clock preserve components in their explicit UTC presentation zone', () => {
  inDeviceZones((zone) => {
    for (const day of days) {
      expect(pickerDate(day, 'date', 'ios').toISOString().slice(0, 10), zone).toBe(day);
      expect(pickerValue(new Date(`${day}T00:00:00Z`), 'date', 'ios'), zone).toBe(day);
      expect(pickerMinimumDate(day, 'ios').toISOString().slice(0, 10), zone).toBe(day);
    }
    for (const time of times) {
      const initial = pickerDate(time, 'time', 'ios');
      expect([initial.getUTCHours(), initial.getUTCMinutes()], zone).toEqual(time.split(':').map(Number));
      const nativeResult = new Date(initial);
      nativeResult.setUTCHours(23, 59, 0, 0);
      expect(pickerValue(nativeResult, 'time', 'ios'), zone).toBe('23:59');
    }
  });
});

test('picker labels remain localized and do not move midnight to another date', () => {
  inDeviceZones((zone) => {
    expect(formatPickerValue('2026-09-21', 'date', 'pl'), zone).toBe('21 września 2026');
    expect(formatPickerValue('2026-09-21', 'date', 'en'), zone).toBe('September 21, 2026');
    expect(formatPickerValue('00:07', 'time', 'pl'), zone).toBe('00:07');
    expect(formatPickerValue('23:59', 'time', 'en'), zone).toBe('23:59');
  });
});

test('picker validation rejects rolled-over dates and incomplete or out-of-range clock values', () => {
  for (const value of ['2026-02-29', '2026-04-31', '2026-13-01', '2026-00-01', '2026-09-00', '2026-9-21', 'invalid', '']) {
    expect(isPickerValue(value, 'date'), value).toBe(false);
  }
  for (const value of ['24:00', '02:60', '2:37', '02:', '02:37:00', 'invalid', '']) {
    expect(isPickerValue(value, 'time'), value).toBe(false);
  }
  expect(isPickerValue('2028-02-29', 'date')).toBe(true);
  expect(isPickerValue('02:37', 'time')).toBe(true);
});
