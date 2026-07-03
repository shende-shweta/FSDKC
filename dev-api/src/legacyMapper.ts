/**
 * Legacy Data Mapper — Safe array key access (no extract())
 * AC-C03: TypeScript migration — eliminates extract() anti-pattern
 * 
 * Mirrors PHP backend/app/Legacy/LegacyDataMapper.php (fixed version)
 */

import type { ReportRow, JobContext } from './types.js';

/**
 * Maps a legacy report row to a standardized format.
 * 
 * @param row - Raw row object with optional name, reachability_pct, country_code
 * @returns Standardized report row with safe defaults
 */
export function mapReportRow(row: Record<string, unknown>): ReportRow {
  return {
    label: typeof row['name'] === 'string' ? row['name'] : 'Unknown',
    metric: typeof row['reachability_pct'] === 'number' ? row['reachability_pct'] : 0,
    region: typeof row['country_code'] === 'string' ? row['country_code'] : 'N/A',
    source: 'legacy_mapper',
  };
}

/**
 * Maps a legacy job context object.
 * 
 * @param context - Raw context object with optional job_name, phone_number, menu_depth
 * @returns Standardized job context with safe defaults
 */
export function mapJobContext(context: Record<string, unknown>): JobContext {
  return {
    job_name: typeof context['job_name'] === 'string' ? context['job_name'] : null,
    phone: typeof context['phone_number'] === 'string' ? context['phone_number'] : null,
    depth: typeof context['menu_depth'] === 'number' ? context['menu_depth'] : 0,
  };
}
