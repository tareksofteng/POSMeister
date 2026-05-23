/*
 * Small key/value cache for app settings, branches and tax rules.
 * Each domain stored under its own row so we can hot-refresh one
 * without touching the others.
 */
import { put, get } from './db';

const STORE = 'settings';

export async function saveSettings(app)    { return put(STORE, { k: 'app',      v: app }); }
export async function saveBranches(rows)   { return put(STORE, { k: 'branches', v: rows }); }
export async function saveTaxRules(rows)   { return put(STORE, { k: 'tax',      v: rows }); }

export async function loadSettings()  { return (await get(STORE, 'app'))?.v       ?? null; }
export async function loadBranches()  { return (await get(STORE, 'branches'))?.v  ?? []; }
export async function loadTaxRules()  { return (await get(STORE, 'tax'))?.v       ?? []; }
