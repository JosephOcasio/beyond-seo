import { Base44Logo } from "@/components/Base44Logo";
import { Button } from "@/components/ui/button";

export default function PublicLanding() {
  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 via-white to-orange-50/30">
      <div className="max-w-4xl mx-auto px-6 py-16 space-y-8">
        <header className="space-y-4">
          <h1 className="text-4xl font-semibold text-slate-900 tracking-tight">
            <span className="inline-flex items-center gap-3 align-middle">
              <Base44Logo className="w-10 h-10" />
              <span className="font-bold">AURA Governance Hub</span>
            </span>
          </h1>
          <p className="text-slate-600 text-lg max-w-2xl">
            Enterprise governance platform for controlled decision workflows.
          </p>
        </header>

        <section className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
          <h2 className="text-xl font-semibold text-slate-900">Public Overview</h2>
          <p className="text-slate-700">
            This site is intentionally limited to high-level context.
            Internal methods, model behavior, and review artifacts are not exposed on public pages.
          </p>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
            <div className="rounded-lg border border-slate-200 p-4">
              <div className="font-medium text-slate-900">Policy Controls</div>
              <div className="text-slate-600 mt-1">
                Release controls for regulated workflows.
              </div>
            </div>
            <div className="rounded-lg border border-slate-200 p-4">
              <div className="font-medium text-slate-900">Operational Audit</div>
              <div className="text-slate-600 mt-1">
                Immutable event history for internal compliance.
              </div>
            </div>
            <div className="rounded-lg border border-slate-200 p-4">
              <div className="font-medium text-slate-900">Access Model</div>
              <div className="text-slate-600 mt-1">
                Public presentation with private review channels.
              </div>
            </div>
          </div>
        </section>

        <section className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-3">
          <h2 className="text-xl font-semibold text-slate-900">Access</h2>
          <p className="text-slate-700">
            If you need the technical packet or evaluator access, request a private session.
          </p>
          <div className="flex flex-wrap gap-3">
            <Button asChild>
              <a href="mailto:joeocasio91@gmail.com?subject=AURA%20Governance%20Hub%20-%20Private%20Access%20Request">
                Request Private Access
              </a>
            </Button>
            <Button variant="outline" asChild>
              <a href="mailto:joeocasio91@gmail.com?subject=AURA%20Governance%20Hub%20-%20General%20Inquiry">
                Contact
              </a>
            </Button>
          </div>
        </section>
      </div>
    </div>
  );
}
