import { useServiceStatus } from '../lib/useServiceStatus.js';

export default function ServiceStatus() {
  const state = useServiceStatus();
  if (state !== 'down') return null;
  return (
    <p className="status" role="status">
      <span className="status-dot" aria-hidden="true" />
      Service de contact indisponible
    </p>
  );
}