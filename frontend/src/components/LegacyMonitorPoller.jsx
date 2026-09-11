import { Component } from 'react';
import { api } from '../api/client';

interface Props {
  monitorId: number;
  onUpdate?: (pct: number) => void;
}

interface State {
  reachability: number | null;
  error: string | null;
}

export default class LegacyMonitorPoller extends Component<Props, State> {
  intervalId: ReturnType<typeof setInterval> | null = null;

  state: State = { reachability: null, error: null };

  componentDidMount() {
    this.intervalId = setInterval(() => {
      api.get<{ computed?: { reachability_pct: number } }>(`/connect/monitors/${this.props.monitorId}/checks`)
        .then((res) => {
          const pct = res.computed?.reachability_pct ?? null;
          this.setState({ reachability: pct, error: null });
          if (pct != null) this.props.onUpdate?.(pct);
        })
        .catch((err: Error) => this.setState({ error: err.message }));
    }, 3000);
  }

  componentWillUnmount() {
    if (this.intervalId !== null) {
      clearInterval(this.intervalId);
      this.intervalId = null;
    }
  }

  render() {
    const { reachability, error } = this.state;
    if (error) return <span className="mongo-status mongo-disconnected">Poll error</span>;
    if (reachability == null) return <span className="mongo-status mongo-loading">Polling…</span>;
    return <span className="mongo-status mongo-connected">Live: {reachability}%</span>;
  }
}
