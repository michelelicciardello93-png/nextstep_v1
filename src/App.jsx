import { useState } from 'react';
import { api } from './app/api';

export default function App() {
  const [setupStatus, setSetupStatus] = useState(null);
  const [message, setMessage] = useState('');

  const checkSetup = async () => {
    try {
      const res = await api('setup/status');
      setSetupStatus(res);
      setMessage('Setup status loaded.');
    } catch (error) {
      setMessage(error.message);
    }
  };

  return (
    <main style={{ padding: 24, fontFamily: 'Inter, Arial, sans-serif' }}>
      <h1>NextStep v1</h1>
      <p>Foundation mode: database setup, superadmin creation, workspace auth, and roles.</p>

      <section style={{ marginTop: 24 }}>
        <h2>Component 1 — Setup</h2>
        <button onClick={checkSetup}>Check setup status</button>
        {message && <p>{message}</p>}
        {setupStatus && (
          <pre>{JSON.stringify(setupStatus, null, 2)}</pre>
        )}
      </section>
    </main>
  );
}
