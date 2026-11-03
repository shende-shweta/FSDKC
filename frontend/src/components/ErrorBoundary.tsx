import { Component, type ErrorInfo, type ReactNode } from 'react';

interface Props {
  children: ReactNode;
  fallback?: ReactNode;
}

interface State {
  hasError: boolean;
  message: string;
}

export default class ErrorBoundary extends Component<Props, State> {
  state: State = { hasError: false, message: '' };

  static getDerivedStateFromError(error: Error): State {
    return { hasError: true, message: error.message };
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    console.error('[ErrorBoundary]', error, info.componentStack);
  }

  handleReset = (): void => {
    this.setState({ hasError: false, message: '' });
  };

  render(): ReactNode {
    if (this.state.hasError) {
      if (this.props.fallback) return this.props.fallback;

      return (
        <div role="alert" style={{ padding: '2rem', textAlign: 'center' }}>
          <p style={{ color: 'var(--danger, #e53e3e)', marginBottom: '1rem' }}>
            Something went wrong: {this.state.message}
          </p>
          <button onClick={this.handleReset} style={{ cursor: 'pointer' }}>
            Try again
          </button>
        </div>
      );
    }

    return this.props.children;
  }
}
