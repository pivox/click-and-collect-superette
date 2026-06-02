"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from "react";
import { listClientNotifications } from "@/lib/services/client-notifications.service";
import { useClientAuth } from "@/lib/auth/ClientAuthContext";

const POLL_INTERVAL_MS = 60_000;

interface ClientNotificationsContextValue {
  unreadCount: number;
  refreshUnread: () => Promise<void>;
}

const ClientNotificationsContext = createContext<ClientNotificationsContextValue>({
  unreadCount: 0,
  refreshUnread: async () => {},
});

export function ClientNotificationsProvider({ children }: { children: React.ReactNode }) {
  const { user } = useClientAuth();
  const [unreadCount, setUnreadCount] = useState(0);
  const latestRequestId = useRef(0);
  const isMounted = useRef(false);

  const refreshUnread = useCallback(async () => {
    if (!user) return;
    const requestId = latestRequestId.current + 1;
    latestRequestId.current = requestId;
    try {
      const data = await listClientNotifications({ unread: true });
      if (!isMounted.current || requestId !== latestRequestId.current) return;
      setUnreadCount(data.total);
    } catch {
      if (!isMounted.current || requestId !== latestRequestId.current) return;
      setUnreadCount(0);
    }
  }, [user]);

  useEffect(() => {
    isMounted.current = true;
    void refreshUnread();

    const interval = setInterval(() => void refreshUnread(), POLL_INTERVAL_MS);
    window.addEventListener("client-notifications:refresh", refreshUnread);

    return () => {
      isMounted.current = false;
      latestRequestId.current += 1;
      clearInterval(interval);
      window.removeEventListener("client-notifications:refresh", refreshUnread);
    };
  }, [refreshUnread]);

  return (
    <ClientNotificationsContext.Provider value={{ unreadCount, refreshUnread }}>
      {children}
    </ClientNotificationsContext.Provider>
  );
}

export function useClientNotifications() {
  return useContext(ClientNotificationsContext);
}
