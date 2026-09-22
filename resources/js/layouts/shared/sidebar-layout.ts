// Single source untuk lebar sidebar agar offset secondary dan margin layout
// tidak divergen. Nilai Tailwind ditulis literal di sini supaya tetap terdeteksi
// oleh scanner (jangan bangun via template string dinamis).

export const SIDEBAR_WIDTH = 290;
export const SIDEBAR_COLLAPSED_WIDTH = 90;
export const SECONDARY_WIDTH = 240;

export const SIDEBAR_OFFSET = {
    expanded: 'lg:left-[290px]',
    collapsed: 'lg:left-[90px]',
} as const;

export const LAYOUT_MARGIN = {
    settingsExpanded: 'lg:ml-[530px]',
    settingsCollapsed: 'lg:ml-[330px]',
    expanded: 'lg:ml-[290px]',
    collapsed: 'lg:ml-[90px]',
} as const;
