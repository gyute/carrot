/**
 * The form language of the portal gate, shared by every screen under
 * pages/auth so login, registration and the password screens stay one design.
 *
 * Fields are white with a hairline border rather than grey fills: on the
 * frosted card a filled box reads as disabled, while a bordered one reads as
 * editable. Every control lands on the same radius and the same 3rem height,
 * which is what keeps the rows lined up when a field sits beside a button.
 */
export const portalFieldClasses =
    'h-12 rounded-lg border border-slate-200 bg-white px-4 text-base text-slate-800 shadow-xs transition placeholder:text-slate-400 hover:border-slate-300 focus-visible:border-sky-500 focus-visible:ring-4 focus-visible:ring-sky-500/15';

/** Small caps-ish label sitting above a field. */
export const portalLabelClasses =
    'text-xs font-bold tracking-wide text-slate-500';

/** Primary action of a gate form. Pair with cn() to override height or size. */
export const portalSubmitClasses =
    'h-12 w-full rounded-lg bg-sky-600 text-base font-bold text-white shadow-sm transition hover:bg-sky-700 active:bg-sky-800';

/** Secondary action rendered as an outlined button on the card. */
export const portalOutlineClasses =
    'h-12 w-full rounded-lg border-slate-200 bg-white text-slate-700 shadow-xs transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900';

export const portalCheckboxClasses =
    'size-[1.125rem] rounded-[5px] border-slate-300 shadow-xs data-[state=checked]:border-sky-600 data-[state=checked]:bg-sky-600';
