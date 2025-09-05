import React from 'react';
import { createRoot } from 'react-dom/client';
import './bootstrap';
import App from './App';

const container = document.getElementById('root');
if (container) {
  const root = createRoot(container);
  root.render(<App />);
}