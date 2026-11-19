import { useEffect, useRef, useState } from 'react';
import { copyText } from '../lib/clipboard.js';

export default function CopyButton({ text }) {
  const [copied, setCopied] = useState(false);
  const timer = useRef(null);

  useEffect(() => () => clearTimeout(timer.current), []);

  async function onClick() {
    if (!(await copyText(text))) return;
    setCopied(true);
    clearTimeout(timer.current);
    timer.current = setTimeout(() => setCopied(false), 1800);
  }

  return (
    <>
      <button type="button" className={`copy${copied ? ' done' : ''}`} onClick={onClick} aria-label={`Copier ${text}`}>
        {copied ? 'Copié' : 'Copier'}
      </button>
      <span className="sr-only" role="status">{copied ? 'Adresse copiée' : ''}</span>
    </>
  );
}