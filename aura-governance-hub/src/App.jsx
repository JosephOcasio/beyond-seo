import { lazy, Suspense } from "react";
import PublicLanding from "@/components/PublicLanding";

const INTERNAL_CONSOLE_ENABLED =
  import.meta.env.DEV || import.meta.env.VITE_ENABLE_INTERNAL_CONSOLE === "true";
const InternalConsole = INTERNAL_CONSOLE_ENABLED
  ? lazy(() => import("@/components/InternalConsole"))
  : null;

function normalizePathname(pathname) {
  const trimmed = pathname.replace(/\/+$/, "");
  return trimmed || "/";
}

export default function App() {
  const path = normalizePathname(window.location.pathname);

  if (path === "/internal") {
    if (!INTERNAL_CONSOLE_ENABLED || !InternalConsole) {
      return <PublicLanding />;
    }

    return (
      <Suspense fallback={<div className="min-h-screen bg-slate-100" />}>
        <InternalConsole />
      </Suspense>
    );
  }

  return <PublicLanding />;
}
