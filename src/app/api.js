const API_BASE = '/backend/api';

export async function api(path, options = {}) {
  const res = await fetch(`${API_BASE}/${path}`, {
    method: options.method || 'GET',
    headers: {
      'Content-Type': 'application/json'
    },
    body: options.body ? JSON.stringify(options.body) : undefined
  });

  let data;
  try {
    data = await res.json();
  } catch (e) {
    throw new Error('Invalid JSON response');
  }

  if (!res.ok) {
    throw new Error(data?.error || 'API error');
  }

  return data;
}
