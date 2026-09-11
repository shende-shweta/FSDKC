import { Component } from 'react';
import { api } from '../api/client';
import { endpoints } from '../api/endpoints';

// Dead code: this component is not imported anywhere in the application.
// Retained as a reference for the setInterval lifecycle fix (mount/unmount pattern).
// TypeScript-specific syntax has been removed so this file is valid JSX if ever imported.
export default class LegacyMonitorPoller extends Component {
  intervalId = null;
  state = { reachability: null, error: null };

  componentDidMount() {
    this.intervalId = setInterval(() => {
      api.get(endpoints.connect.checks(this.props.monitorId))
        .then((res) => {
          const pct = res.computed?.reachability_pct ?? null;
          this.setState({ reachability: pct, error: null });
          if (pct != null) this.props.onUpdate?.(pct);
        })
        .catch((err) => this.setState({ error: err.message }));
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
    if (reachability == null) return <span className="mongo-status mongo-loading">Polling\u2026</span>;
    return <span className="mongo-status mongo-connected">Live: {reachability}%</span>;
  }
}
