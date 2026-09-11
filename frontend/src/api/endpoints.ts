export const endpoints = {
  health: '/health',
  mongodb: {
    status: '/mongodb/status',
    transcripts: (module: string, referenceId: number) =>
      `/mongodb/transcripts?module=${module}&reference_id=${referenceId}`,
    diagnostics: (module: string, referenceId: number) =>
      `/mongodb/diagnostics/${module}/${referenceId}`,
  },
  dashboard: {
    kpis: '/dashboard/kpis',
  },
  discovery: {
    jobs: '/discovery/jobs',
    job: (id: number) => `/discovery/jobs/${id}`,
    tree: (id: number) => `/discovery/jobs/${id}/tree`,
    start: (id: number) => `/discovery/jobs/${id}/start`,
    stream: (id: number, sessionId: string) =>
      `/discovery/jobs/${id}/stream?session_id=${sessionId}`,
  },
  connect: {
    monitors: '/connect/monitors',
    monitor: (id: number) => `/connect/monitors/${id}`,
    checks: (id: number) => `/connect/monitors/${id}/checks`,
    runCheck: (id: number) => `/connect/monitors/${id}/run-check`,
    stream: (id: number, sessionId: string) =>
      `/connect/monitors/${id}/stream?session_id=${sessionId}`,
  },
  legacy: {
    carriers: '/legacy/reports/carriers',
    ivr: (jobId: number) => `/legacy/reports/ivr/${jobId}`,
  },
} as const;
