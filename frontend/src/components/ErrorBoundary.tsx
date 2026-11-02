import { Component, type ErrorInfo, type ReactNode } from 'react';

interface Props {
  children: ReactNode;
}

interface State {
  hasError: boolean;
}

export default class ErrorBoundary extends Component<Props, State> {
  state: State = { hasError: false };

  static getDerivedStateFromError(): State {
    return { hasError: true };
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    console.error('ErrorBoundary caught:', error, info.componentStack);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="card" style={{ margin: '2rem', padding: '2rem', textAlign: 'center' }}>
          <p style={{ marginBottom: '1rem', color: 'var(--danger)' }}>
            Something went wrong. Please refresh.
          </p>
          <button
            onClick={() => window.location.reload()}
            style={{ padding: '0.5rem 1.25rem', background: 'var(--accent)', color: '#fff', border: 'none', borderRadius: '4px', cursor: 'pointer' }}
          >
            Reload
          </button>
        </div>
      );
    }

    return this.props.children;
  }
}
