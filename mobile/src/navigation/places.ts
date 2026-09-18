export type Place = {
  name: string;
  route: string;
  icon: string;
  labelKey: string;
  shortLabelKey?: string;
  needsManaging?: boolean;
};

export const BAR_LIMIT = 4;

const ARRANGEABLE: Place[] = [
  { name: 'tasks', route: 'index', icon: 'checkbox-marked-outline', labelKey: 'nav.tasks' },
  { name: 'teams', route: 'teams', icon: 'account-group-outline', labelKey: 'nav.teams' },
  {
    name: 'allowance',
    route: 'allowance',
    icon: 'wallet-outline',
    labelKey: 'nav.allowance',
    shortLabelKey: 'nav.allowanceShort',
  },
  {
    name: 'personalise',
    route: 'personalise',
    icon: 'star-four-points-outline',
    labelKey: 'personalise.title',
    shortLabelKey: 'personalise.short',
  },
  {
    name: 'task-types',
    route: 'task-types',
    icon: 'format-list-bulleted-type',
    labelKey: 'nav.taskTypes',
    shortLabelKey: 'nav.taskTypesShort',
    needsManaging: true,
  },
  {
    name: 'bonus-rules',
    route: 'bonus-rules',
    icon: 'star-outline',
    labelKey: 'nav.bonusRules',
    shortLabelKey: 'nav.bonusRulesShort',
    needsManaging: true,
  },
  {
    name: 'account',
    route: 'account',
    icon: 'account-circle-outline',
    labelKey: 'nav.account',
    shortLabelKey: 'nav.accountShort',
  },
  { name: 'action-plans', route: 'action-plans', icon: 'format-list-checks', labelKey: 'actionPlans.title', shortLabelKey: 'actionPlans.shortTitle' },
  { name: 'settings', route: 'settings', icon: 'cog-outline', labelKey: 'nav.settings' },
];

const FOR_SUPER_ADMIN: Place[] = [
  {
    name: 'status-change-rules',
    route: 'status-change-rules',
    icon: 'gavel',
    labelKey: 'nav.statusChangeRules',
    shortLabelKey: 'nav.statusChangeRulesShort',
  },
  {
    name: 'notification-events',
    route: 'notification-events',
    icon: 'bell-outline',
    labelKey: 'nav.notificationEvents',
    shortLabelKey: 'nav.notificationEventsShort',
  },
];

const BY_NAME = new Map(ARRANGEABLE.map((place) => [place.name, place]));

export type Standing = {
  chosen?: string[];
  built: string[];
  manages: boolean;
  isSuperAdmin: boolean;
};

export const placesToShow = ({ chosen, built, manages, isSuperAdmin }: Standing): Place[] => {
  const exists = (place: Place) => built.includes(place.route);
  const order = chosen?.length ? chosen : ARRANGEABLE.map((place) => place.name);

  const arranged = order
    .map((name) => BY_NAME.get(name))
    .filter((place): place is Place => Boolean(place))
    .filter((place) => !place.needsManaging || manages)
    .filter(exists);

  return [...arranged, ...(isSuperAdmin ? FOR_SUPER_ADMIN.filter(exists) : [])];
};

export const splitAtBar = (places: Place[]): { onBar: Place[]; behindMore: Place[] } =>
  places.length > BAR_LIMIT + 1
    ? { onBar: places.slice(0, BAR_LIMIT), behindMore: places.slice(BAR_LIMIT) }
    : { onBar: places, behindMore: [] };
