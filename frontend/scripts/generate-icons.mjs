import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ICONS = {
    account: 'account_circle',
    add: 'add',
    admin: 'admin_panel_settings',
    approve: 'thumb_up',
    block: 'block',
    calendar: 'calendar_month',
    check: 'check',
    checkCircle: 'check_circle',
    close: 'close',
    copy: 'content_copy',
    darkMode: 'dark_mode',
    delete: 'delete',
    edit: 'edit',
    error: 'error',
    expand: 'keyboard_arrow_down',
    goal: 'flag',
    info: 'info',
    install: 'install_desktop',
    key: 'key',
    language: 'language',
    lightMode: 'light_mode',
    lock: 'lock',
    logout: 'logout',
    mail: 'mail',
    menu: 'menu',
    more: 'more_vert',
    notifications: 'notifications',
    pause: 'do_not_disturb_on',
    person: 'person',
    personRemove: 'person_remove',
    remove: 'remove',
    restore: 'restart_alt',
    rule: 'rule',
    schedule: 'schedule',
    send: 'send',
    settings: 'settings',
    stars: 'stars',
    streak: 'local_fire_department',
    systemMode: 'brightness_auto',
    tasks: 'checklist',
    taskTypes: 'category',
    teamAdd: 'group_add',
    teams: 'groups',
    trophy: 'trophy',
    savings: 'savings',
    undo: 'undo',
    wallet: 'wallet',
    workspacePremium: 'workspace_premium',
};

const here = path.dirname(fileURLToPath(import.meta.url));
const source = path.join(here, '..', 'node_modules', '@material-symbols', 'svg-400', 'rounded');

const entries = Object.entries(ICONS).map(([key, name]) => {
    const svg = fs.readFileSync(path.join(source, `${name}.svg`), 'utf8');
    const paths = [...svg.matchAll(/<path d="([^"]+)"/g)].map((match) => match[1]);
    if (paths.length === 0) {
        throw new Error(`No path data in ${name}.svg`);
    }
    return `    ${key}: ${JSON.stringify(paths.join(' '))},`;
}).join('\n');

const module = `export const ICON_PATHS = {
${entries}
};
`;

const target = path.join(here, '..', 'src', 'components', 'md3', 'iconPaths.js');
fs.mkdirSync(path.dirname(target), { recursive: true });
fs.writeFileSync(target, module);
console.log(`Generated ${path.relative(process.cwd(), target)} with ${Object.keys(ICONS).length} icons`);
