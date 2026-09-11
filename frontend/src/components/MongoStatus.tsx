import { useQuery } from '@tanstack/react-query';
import { api } from '../api/client';
import { endpoints } from '../api/endpoints';
import type { MongoHealth } from '../types';

export default function MongoStatus() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['mongodb', 'status'],
    queryFn: () => api.get<MongoHealth>(endpoints.mongodb.status),
    refetchInterval: 15_000,
  });

  if (isLoading) return <span className="mongo-status mongo-loading">MongoDB\u2026</span>;
  if (isError || !data?.connected) {
    return <span className="mongo-status mongo-disconnected">MongoDB offline</span>;
  }

  return (
    <span className="mongo-status mongo-connected" title={`${data.database ?? 'klearcom'} (${data.mode})`}>
      MongoDB \u25cf {data.database} \u00b7 {data.collections?.transcripts ?? 0} docs
    </span>
  );
}
