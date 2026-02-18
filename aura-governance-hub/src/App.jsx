import PublicLanding from "@/components/PublicLanding";
import InternalConsole from "@/components/InternalConsole";

function normalizePathname(pathname) {
  const trimmed = pathname.replace(/\/+$/, "");
  return trimmed || "/";
}

export default function App() {
  const path = normalizePathname(window.location.pathname);

  if (path === "/internal") {
    return <InternalConsole />;
  }

  return <PublicLanding />;
}
