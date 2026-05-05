import React, { useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { api } from './app/api.js';

const h = React.createElement;

const roleLinks = {
  superadmin: ['Dashboard', 'Users', 'Auth codes', 'Workspace settings', 'System settings', 'Logs'],
  admin: ['Dashboard', 'Users', 'Processes', 'Logs', 'Locales'],
  supervisor: ['Dashboard', 'Run process', 'Logs / QA'],
  agent: ['Dashboard', 'Run process', 'My history'],
};

const emptySetup = {
  workspace_name: '',
  full_name: '',
  email: '',
  password: '',
  confirm_password: '',
};

const emptyLogin = { email: '', password: '' };
const emptyRegistration = { full_name: '', email: '', password: '', confirm_password: '', auth_code: '' };
const emptyInvite = { role: 'agent', max_uses: 10, expires_at: '', label: '' };

function Field({ label, name, type = 'text', value, onChange, placeholder }) {
  return h(
    'label',
    { className: 'field' },
    h('span', null, label),
    h('input', {
      name,
      type,
      value,
      placeholder,
      onChange: (event) => onChange(event.target.name, event.target.value),
    }),
  );
}

function SelectField({ label, name, value, onChange, options }) {
  return h(
    'label',
    { className: 'field' },
    h('span', null, label),
    h(
      'select',
      { name, value, onChange: (event) => onChange(event.target.name, event.target.value) },
      options.map((option) => h('option', { key: option, value: option }, option)),
    ),
  );
}

function Notice({ error, success }) {
  if (!error && !success) return null;
  return h('div', { className: error ? 'notice error' : 'notice success' }, error || success);
}

function SetupScreen({ onReady }) {
  const [form, setForm] = useState(emptySetup);
  const [message, setMessage] = useState({ error: '', success: '' });
  const [loading, setLoading] = useState(false);
  const update = (name, value) => setForm((current) => ({ ...current, [name]: value }));

  async function submit(event) {
    event.preventDefault();
    setLoading(true);
    setMessage({ error: '', success: '' });
    try {
      const result = await api('/setup/create-superadmin', { method: 'POST', body: form });
      setMessage({ error: '', success: 'Superadmin created. Opening dashboard…' });
      onReady(result.user);
    } catch (error) {
      setMessage({ error: error.message, success: '' });
    } finally {
      setLoading(false);
    }
  }

  return h(
    'main',
    { className: 'auth-shell' },
    h(
      'section',
      { className: 'card hero-card' },
      h('p', { className: 'eyebrow' }, 'First launch'),
      h('h1', null, 'Create first workspace + superadmin'),
      h('p', null, 'Because there are no users yet, registration is open only for the root setup account.'),
    ),
    h(
      'form',
      { className: 'card form-card', onSubmit: submit },
      h(Notice, message),
      h(Field, { label: 'Workspace name', name: 'workspace_name', value: form.workspace_name, onChange: update, placeholder: 'Zooplus Italy CC' }),
      h(Field, { label: 'Full name', name: 'full_name', value: form.full_name, onChange: update, placeholder: 'Michele Rossi' }),
      h(Field, { label: 'Email', name: 'email', type: 'email', value: form.email, onChange: update, placeholder: 'you@example.com' }),
      h(Field, { label: 'Password', name: 'password', type: 'password', value: form.password, onChange: update }),
      h(Field, { label: 'Confirm password', name: 'confirm_password', type: 'password', value: form.confirm_password, onChange: update }),
      h('button', { disabled: loading }, loading ? 'Creating…' : 'Create superadmin'),
    ),
  );
}

function AuthScreen({ onReady }) {
  const [mode, setMode] = useState('login');
  const [login, setLogin] = useState(emptyLogin);
  const [registration, setRegistration] = useState(emptyRegistration);
  const [message, setMessage] = useState({ error: '', success: '' });
  const [loading, setLoading] = useState(false);

  const form = mode === 'login' ? login : registration;
  const update = (name, value) => {
    if (mode === 'login') setLogin((current) => ({ ...current, [name]: value }));
    else setRegistration((current) => ({ ...current, [name]: value }));
  };

  async function submit(event) {
    event.preventDefault();
    setLoading(true);
    setMessage({ error: '', success: '' });
    try {
      const result = await api(mode === 'login' ? '/auth/login' : '/auth/register', { method: 'POST', body: form });
      onReady(result.user);
    } catch (error) {
      setMessage({ error: error.message, success: '' });
    } finally {
      setLoading(false);
    }
  }

  return h(
    'main',
    { className: 'auth-shell' },
    h(
      'section',
      { className: 'card hero-card' },
      h('p', { className: 'eyebrow' }, 'NextStep'),
      h('h1', null, mode === 'login' ? 'Log in to your workspace' : 'Create account with auth code'),
      h('p', null, 'After the first superadmin exists, every new account must use an invite/auth code.'),
      h(
        'div',
        { className: 'segmented' },
        h('button', { type: 'button', className: mode === 'login' ? 'active' : '', onClick: () => setMode('login') }, 'Login'),
        h('button', { type: 'button', className: mode === 'register' ? 'active' : '', onClick: () => setMode('register') }, 'Register'),
      ),
    ),
    h(
      'form',
      { className: 'card form-card', onSubmit: submit },
      h(Notice, message),
      mode === 'register' ? h(Field, { label: 'Full name', name: 'full_name', value: registration.full_name, onChange: update }) : null,
      h(Field, { label: 'Email', name: 'email', type: 'email', value: form.email, onChange: update }),
      h(Field, { label: 'Password', name: 'password', type: 'password', value: form.password, onChange: update }),
      mode === 'register' ? h(React.Fragment, null,
        h(Field, { label: 'Confirm password', name: 'confirm_password', type: 'password', value: registration.confirm_password, onChange: update }),
        h(Field, { label: 'Auth code', name: 'auth_code', value: registration.auth_code, onChange: update, placeholder: 'ZOO-AGENT-2026' }),
      ) : null,
      h('button', { disabled: loading }, loading ? 'Please wait…' : mode === 'login' ? 'Log in' : 'Create account'),
    ),
  );
}

function Dashboard({ user, onLogout }) {
  const links = roleLinks[user.role] || roleLinks.agent;
  const [users, setUsers] = useState([]);
  const [invite, setInvite] = useState(emptyInvite);
  const [createdCode, setCreatedCode] = useState('');
  const [message, setMessage] = useState({ error: '', success: '' });

  const canManageUsers = ['superadmin', 'admin'].includes(user.role);
  const permissionSummary = useMemo(() => ({
    superadmin: 'Global owner access: workspaces, users, roles, processes, logs, and system settings.',
    admin: 'Workspace builder access: users, processes, questions, outcomes, logs, and locales.',
    supervisor: 'Operational reviewer access: process running, activity review, logs, and QA.',
    agent: 'Frontline access: assigned processes, outcomes, allowed actions, and own history.',
  }[user.role]), [user.role]);

  async function loadUsers() {
    if (!canManageUsers) return;
    try {
      const result = await api('/users');
      setUsers(result.users);
    } catch (error) {
      setMessage({ error: error.message, success: '' });
    }
  }

  useEffect(() => {
    loadUsers();
  }, []);

  async function submitInvite(event) {
    event.preventDefault();
    setMessage({ error: '', success: '' });
    setCreatedCode('');
    try {
      const result = await api('/users/invite-code', { method: 'POST', body: invite });
      setCreatedCode(result.code);
      setMessage({ error: '', success: `Created ${result.role} auth code.` });
      setInvite(emptyInvite);
    } catch (error) {
      setMessage({ error: error.message, success: '' });
    }
  }

  async function logout() {
    await api('/auth/logout', { method: 'POST' });
    onLogout();
  }

  const updateInvite = (name, value) => setInvite((current) => ({ ...current, [name]: value }));

  return h(
    'main',
    { className: 'app-shell' },
    h(
      'aside',
      { className: 'sidebar' },
      h('div', null,
        h('p', { className: 'eyebrow' }, 'Workspace'),
        h('h2', null, user.workspace_name),
        h('p', null, user.full_name),
        h('span', { className: 'role-pill' }, user.role),
      ),
      h('nav', null, links.map((link) => h('a', { key: link, href: `#${link.toLowerCase().replaceAll(' ', '-')}` }, link))),
      h('button', { className: 'secondary', onClick: logout }, 'Log out'),
    ),
    h(
      'section',
      { className: 'content' },
      h('div', { className: 'page-header' },
        h('p', { className: 'eyebrow' }, 'Dashboard'),
        h('h1', null, `Welcome back, ${user.full_name}`),
        h('p', null, permissionSummary),
      ),
      h('div', { className: 'grid' },
        h('article', { className: 'card' },
          h('h3', null, 'Access model'),
          h('ul', { className: 'check-list' },
            h('li', null, 'Every user belongs to one workspace.'),
            h('li', null, 'First user is the active superadmin.'),
            h('li', null, 'Later registration requires an auth code.'),
            h('li', null, 'Sessions use HTTP-only cookies and hashed DB tokens.'),
          ),
        ),
        canManageUsers ? h('article', { className: 'card' },
          h('h3', null, 'Create auth code'),
          h(Notice, message),
          createdCode ? h('div', { className: 'code-box' }, createdCode) : null,
          h('form', { className: 'compact-form', onSubmit: submitInvite },
            h(Field, { label: 'Label', name: 'label', value: invite.label, onChange: updateInvite, placeholder: 'Italy agents May 2026' }),
            h(SelectField, { label: 'Role', name: 'role', value: invite.role, onChange: updateInvite, options: user.role === 'superadmin' ? ['admin', 'supervisor', 'agent'] : ['supervisor', 'agent'] }),
            h(Field, { label: 'Max uses', name: 'max_uses', type: 'number', value: invite.max_uses, onChange: updateInvite }),
            h(Field, { label: 'Expires at', name: 'expires_at', type: 'datetime-local', value: invite.expires_at, onChange: updateInvite }),
            h('button', null, 'Create invite code'),
          ),
        ) : null,
      ),
      canManageUsers ? h('article', { className: 'card table-card' },
        h('div', { className: 'table-header' },
          h('h3', null, 'Users'),
          h('button', { className: 'secondary', onClick: loadUsers }, 'Refresh'),
        ),
        h('div', { className: 'table-wrap' },
          h('table', null,
            h('thead', null, h('tr', null,
              h('th', null, 'Name'),
              h('th', null, 'Email'),
              h('th', null, 'Workspace'),
              h('th', null, 'Role'),
              h('th', null, 'Status'),
            )),
            h('tbody', null, users.map((item) => h('tr', { key: item.id },
              h('td', null, item.full_name),
              h('td', null, item.email),
              h('td', null, item.workspace_name),
              h('td', null, item.role),
              h('td', null, item.status),
            ))),
          ),
        ),
      ) : null,
    ),
  );
}

function App() {
  const [booting, setBooting] = useState(true);
  const [requiresSetup, setRequiresSetup] = useState(false);
  const [user, setUser] = useState(null);
  const [error, setError] = useState('');

  async function boot() {
    setBooting(true);
    setError('');
    try {
      const result = await api('/setup/status');
      setRequiresSetup(result.requires_setup);
      setUser(result.user);
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setBooting(false);
    }
  }

  useEffect(() => {
    boot();
  }, []);

  if (booting) return h('div', { className: 'loading' }, 'Loading NextStep…');
  if (error) return h('div', { className: 'loading error' }, error);
  if (requiresSetup && !user) return h(SetupScreen, { onReady: (nextUser) => { setUser(nextUser); setRequiresSetup(false); } });
  if (!user) return h(AuthScreen, { onReady: setUser });
  return h(Dashboard, { user, onLogout: () => setUser(null) });
}

createRoot(document.getElementById('root')).render(h(App));
