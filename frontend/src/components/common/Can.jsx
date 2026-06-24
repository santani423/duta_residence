import { useAuth } from '../../state/AuthContext.jsx';

export default function Can({ permission, any, all, fallback = null, children }) {
  const { can, canAny, canAll } = useAuth();

  if (permission && !can(permission)) return fallback;
  if (any?.length && !canAny(any)) return fallback;
  if (all?.length && !canAll(all)) return fallback;

  return children;
}
