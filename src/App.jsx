import { useState } from 'react';
import { api } from './app/api';

export default function App() {
  const [runId, setRunId] = useState(null);
  const [node, setNode] = useState(null);
  const [completed, setCompleted] = useState(false);

  const installDemo = async () => {
    const res = await api('setup/install-demo', { method: 'POST' });
    alert('Demo installed. Process ID: ' + res.process_id);
  };

  const start = async () => {
    const res = await api('runs/start', {
      method: 'POST',
      body: { process_id: 1, version_id: 1 }
    });

    setRunId(res.run_id);
    setNode(res.node);
    setCompleted(res.completed);
  };

  const step = async (answer) => {
    const res = await api('runs/step', {
      method: 'POST',
      body: { run_id: runId, answer }
    });

    setNode(res.node);
    setCompleted(res.completed);
  };

  return (
    <div style={{ padding: 20 }}>
      <h1>NextStep v1</h1>

      {!runId && (
        <>
          <button onClick={installDemo}>Install Demo</button>
          <button onClick={start} style={{ marginLeft: 10 }}>Start Flow</button>
        </>
      )}

      {node && !completed && (
        <div>
          <h2>{node.title}</h2>
          {node.options?.map(opt => (
            <button key={opt} onClick={() => step(opt)} style={{ marginRight: 10 }}>
              {opt}
            </button>
          ))}
        </div>
      )}

      {node && completed && (
        <div>
          <h2>Outcome:</h2>
          <p>{node.title}</p>
        </div>
      )}
    </div>
  );
}
