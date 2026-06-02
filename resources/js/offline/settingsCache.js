/*
 * Small key/value cache for app settings, branches, tax rules and the
 * lookup tables (suppliers, categories, brands, units) that populate
 * form dropdowns when the cashier creates a product or purchase offline.
 *
 * Each domain stored under its own row so we can hot-refresh one
 * without touching the others.
 */
import { put, get } from './db';

const STORE = 'settings';

export async function saveSettings(app)         { return put(STORE, { k: 'app',              v: app }); }
export async function saveBranches(rows)        { return put(STORE, { k: 'branches',         v: rows }); }
export async function saveTaxRules(rows)        { return put(STORE, { k: 'tax',              v: rows }); }
export async function saveSuppliers(rows)       { return put(STORE, { k: 'suppliers',        v: rows }); }
export async function saveCategories(rows)      { return put(STORE, { k: 'categories',       v: rows }); }
export async function saveBrands(rows)          { return put(STORE, { k: 'brands',           v: rows }); }
export async function saveUnits(rows)           { return put(STORE, { k: 'units',            v: rows }); }
export async function saveRecentSales(rows)     { return put(STORE, { k: 'recent_sales',     v: rows }); }
export async function saveRecentPurchases(rows) { return put(STORE, { k: 'recent_purchases', v: rows }); }

export async function loadSettings()         { return (await get(STORE, 'app'))?.v              ?? null; }
export async function loadBranches()         { return (await get(STORE, 'branches'))?.v         ?? []; }
export async function loadTaxRules()         { return (await get(STORE, 'tax'))?.v              ?? []; }
export async function loadSuppliers()        { return (await get(STORE, 'suppliers'))?.v        ?? []; }
export async function loadCategories()       { return (await get(STORE, 'categories'))?.v       ?? []; }
export async function loadBrands()           { return (await get(STORE, 'brands'))?.v           ?? []; }
export async function loadUnits()            { return (await get(STORE, 'units'))?.v            ?? []; }
export async function loadRecentSales()      { return (await get(STORE, 'recent_sales'))?.v     ?? []; }
export async function loadRecentPurchases()  { return (await get(STORE, 'recent_purchases'))?.v ?? []; }
