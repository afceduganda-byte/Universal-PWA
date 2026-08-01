
import { useEffect, useState, useRef, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '@/contexts/AuthContext';
import { hasUniversalAccess, shouldBypassAuth, forceAdminAccess } from '@/utils/adminUtils';
import AccessMessage from './AccessMessage';
import { supabase } from '@/integrations/supabase/client';

type DenialReason = 'auth' | 'admin' | 'plan' | 'payment' | 'unknown';

interface ProtectedRouteProps {
  children: React.ReactNode;
  requireAuth?: boolean;
  requireAdmin?: boolean;
  requirePlan?: boolean;
  requirePayment?: boolean;
  redirectTo?: string;
  accessMessage?: string;
  /** Custom loading component to show instead of generic spinner */
  loadingFallback?: React.ReactNode;
  /** Maximum time to wait before forcing access decision (ms) */
  maxLoadingMs?: number;
  /** @deprecated No-op. Access for paid/plan routes is server-decided via check-access. */
  optimisticPlanAccess?: boolean;
}

// Helper to check if local safari data exists
const hasLocalSafariData = (): boolean => {
  try {
    return !!(
      localStorage.getItem('customizedSafari') ||
      localStorage.getItem('pendingCustomizedSafari') ||
      localStorage.getItem('completedSafariPlanId')
    );
  } catch {
    return false;
  }
};

// Check if user just signed up (within last 15 seconds)
const isJustSignedUp = (): boolean => {
  try {
    const ts = localStorage.getItem('just_signed_up');
    if (!ts) return false;
    const elapsed = Date.now() - parseInt(ts, 10);
    return elapsed < 15000; // 15 second grace window
  } catch {
    return false;
  }
};

// Clear the just_signed_up flag
const clearJustSignedUp = () => {
  try { localStorage.removeItem('just_signed_up'); } catch {}
};

const ProtectedRoute = ({
  children,
  requireAuth = false,
  requireAdmin = false,
  requirePlan = false,
  requirePayment = false,
  redirectTo,
  accessMessage,
  loadingFallback,
  maxLoadingMs = 30000, // Default 30s, but /my-itinerary will use 10s
  optimisticPlanAccess = false
}: ProtectedRouteProps) => {
  const { user, userProfile, loading, isAdmin } = useAuth();
  const navigate = useNavigate();
  const [accessState, setAccessState] = useState<'loading' | 'granted' | 'denied'>('loading');
  const [denialMessage, setDenialMessage] = useState<string>('');
  const [denialReason, setDenialReason] = useState<DenialReason>('unknown');
  const timeoutTriggered = useRef(false);
  const planCheckInFlight = useRef(false);
  const [dbHasPlan, setDbHasPlan] = useState<boolean | null>(null);
  const [dbCanPay, setDbCanPay] = useState<boolean | null>(null);

  // Check if we should force admin access (development mode)
  const forceAccess = shouldBypassAuth() || forceAdminAccess();

  // Determine if we need to wait for userProfile to load
  const needsProfileData = requirePlan || requirePayment;

  /**
   * SECURITY: server-side gate. The DB owns the truth
   * (is_safari_plan_complete / is_safari_plan_deposit_paid via the check-access edge function).
   * The client never grants access on requirePayment based on a profile flag alone.
   */
  const verifyAccessFromServer = useCallback(async (): Promise<{ canPlan: boolean; canPay: boolean } | null> => {
    if (!user) return null;
    if (!requirePlan && !requirePayment) return null;
    if (planCheckInFlight.current) return null;

    planCheckInFlight.current = true;
    try {
      const { data, error } = await supabase.functions.invoke('check-access');
      if (error) {
        console.warn('⚠️ ProtectedRoute: check-access failed:', error.message);
        return null;
      }
      const canPlan = !!data?.canViewItinerary;
      const canPay = !!data?.canViewPortal;
      setDbHasPlan(canPlan);
      setDbCanPay(canPay);
      return { canPlan, canPay };
    } finally {
      planCheckInFlight.current = false;
    }
  }, [user, requirePlan, requirePayment]);

  // Kick off server-side verification early for any gated route.
  useEffect(() => {
    if (!requirePlan && !requirePayment) return;
    if (!user) return;
    if (loading) return;
    if (dbHasPlan === null && dbCanPay === null) {
      void verifyAccessFromServer();
    }
  }, [requirePlan, requirePayment, user, loading, dbHasPlan, dbCanPay, verifyAccessFromServer]);

  // Timeout guard - force decision after maxLoadingMs
  useEffect(() => {
    if (accessState !== 'loading') return;

    const timeout = setTimeout(async () => {
      if (accessState === 'loading') {
        timeoutTriggered.current = true;
        console.warn(`⏰ ProtectedRoute timeout (${maxLoadingMs}ms) reached`);

        // SECURITY: Never grant access on timeout for gated routes.
        // If we can't verify required profile flags in time, deny and guide the user.
        if (requireAuth && !user) {
          setDenialMessage(accessMessage || 'Please sign in to access this page.');
          setDenialReason('auth');
          setAccessState('denied');
          return;
        }

        // For plan/payment gates we require server-validated flags.
        if (needsProfileData) {
          // One last server check on timeout
          const result = await verifyAccessFromServer();
          if (result) {
            if (requirePayment && !result.canPay) {
              setDenialMessage(accessMessage || 'Payment is required to access this page.');
              setDenialReason('payment');
              setAccessState('denied');
              return;
            }
            if (requirePlan && !result.canPlan) {
              setDenialMessage(accessMessage || 'A completed safari plan is required to access this page.');
              setDenialReason('plan');
              setAccessState('denied');
              return;
            }
            setAccessState('granted');
            return;
          }

          setDenialMessage(accessMessage || 'Unable to verify your access requirements. Please try again.');
          setDenialReason(requirePayment ? 'payment' : 'plan');
          setAccessState('denied');
          return;
        }

        // If we get here, this route doesn't require profile data.
        if (user) {
          setAccessState('granted');
        } else {
          setDenialMessage(accessMessage || 'Unable to verify access. Please try again.');
          setDenialReason('auth');
          setAccessState('denied');
        }
      }
    }, maxLoadingMs);

    return () => clearTimeout(timeout);
   }, [accessState, maxLoadingMs, user, userProfile, needsProfileData, optimisticPlanAccess, requirePlan, requirePayment, requireAuth, accessMessage, dbHasPlan, dbCanPay, verifyAccessFromServer]);

  useEffect(() => {
    // Don't re-evaluate if timeout already forced a decision
    if (timeoutTriggered.current) return;

    // STABILITY: Once access is granted, never revert to loading.
    // This prevents admin pages from unmounting/remounting on auth state updates.
    if (accessState === 'granted') return;

    // Don't make decisions while still loading auth
    if (loading) {
      setAccessState('loading');
      return;
    }

    // PRIORITY 1: Force access in development - ALWAYS allow access
    if (forceAccess) {
      setAccessState('granted');
      return;
    }

    // PRIORITY 2: Universal access (developer + super-admin bypass)
    if (hasUniversalAccess(userProfile)) {
      setAccessState('granted');
      return;
    }

    // PRIORITY 3: Admin bypass
    if (isAdmin) {
      setAccessState('granted');
      return;
    }

    // PRIORITY 4: Authentication
    if (requireAuth && !user) {
      setDenialMessage(accessMessage || "Sign in to your account to view your itinerary.");
      setDenialReason('auth');
      setAccessState('denied');
      return;
    }

    // PRIORITY 5: Admin requirement
    if (requireAdmin && (!user || !isAdmin)) {
      setDenialMessage(accessMessage || "Admin access required. Please sign in with an admin account.");
      setDenialReason('admin');
      setAccessState('denied');
      return;
    }

    // PRIORITY 6 + 7: Plan/Payment — SERVER-SIDE TRUTH ONLY.
    // We wait for verifyAccessFromServer() to populate dbHasPlan / dbCanPay,
    // then decide. We never grant on userProfile flags alone.
    if (needsProfileData && user) {
      if (dbHasPlan === null && dbCanPay === null) {
        // Server check still in flight — keep loading
        setAccessState('loading');
        return;
      }

      if (requirePayment && dbCanPay !== true) {
        setDenialMessage(accessMessage || "Pay your safari deposit to unlock the My Safari Portal.");
        setDenialReason('payment');
        setAccessState('denied');
        return;
      }

      if (requirePlan && dbHasPlan !== true) {
        setDenialMessage(accessMessage || "You need an active safari plan to access this page.");
        setDenialReason('plan');
        setAccessState('denied');
        return;
      }
    }

    // ALL CHECKS PASSED - GRANT ACCESS
    clearJustSignedUp(); // Clear the grace flag once access is granted
    setAccessState('granted');
  }, [user, userProfile, loading, isAdmin, requireAuth, requireAdmin, requirePlan, requirePayment, forceAccess, needsProfileData, accessMessage, optimisticPlanAccess, dbHasPlan, dbCanPay]);

  // RENDER BASED ON ACCESS STATE - NO FLASHING
  if (accessState === 'loading') {
    if (loadingFallback) {
      return <>{loadingFallback}</>;
    }
    return (
      <div className="min-h-screen bg-gradient-to-b from-safari-50 to-white flex items-center justify-center">
        <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-safari-500"></div>
      </div>
    );
  }

  if (accessState === 'denied') {
    return <AccessMessage message={denialMessage} />;
  }

  // accessState === 'granted'
  return <>{children}</>;
};

export default ProtectedRoute;
