import { Component } from 'react';
import { Alert, Button } from 'antd';

export default class ErrorBoundary extends Component {
  constructor(props) {
    super(props);
    this.state = { error: null };
  }

  static getDerivedStateFromError(error) {
    return { error };
  }

  render() {
    if (this.state.error) {
      return (
        <div className="fatal-error">
          <Alert
            type="error"
            showIcon
            message="UI gagal dimuat"
            description={this.state.error.message || 'Terjadi error pada aplikasi frontend.'}
            action={<Button onClick={() => window.location.reload()}>Muat ulang</Button>}
          />
        </div>
      );
    }

    return this.props.children;
  }
}
