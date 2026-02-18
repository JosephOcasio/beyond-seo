import { useEffect, useMemo, useState } from "react";
import { base44 } from "@/api/base44Client";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { CheckCircle2, Lock, Unlock, XCircle } from "lucide-react";

const passSampleEvidence = [
  {
    id: "e1",
    text: "Revenue recognized at delivery",
    source_url: "https://docs.example.com/rev",
    confidence: 0.9,
  },
  {
    id: "e2",
    text: "ASC 606 compliance confirmed",
    source_url: "https://sec.gov/filing/123",
    confidence: 0.85,
  },
];

const refuseSampleEvidence = [];
const STORAGE_KEY = "aura_internal_access_key";

export default function InternalConsole() {
  const [moduleName, setModuleName] = useState("ao-audit-engine");
  const [requestId, setRequestId] = useState("");
  const [prompt, setPrompt] = useState("What is revenue recognition policy?");
  const [evidenceText, setEvidenceText] = useState(JSON.stringify(passSampleEvidence, null, 2));
  const [isRunning, setIsRunning] = useState(false);
  const [error, setError] = useState("");
  const [audit, setAudit] = useState(null);

  const [accessKeyInput, setAccessKeyInput] = useState("");
  const [accessKey, setAccessKey] = useState("");
  const [isUnlocked, setIsUnlocked] = useState(false);

  useEffect(() => {
    const saved = window.sessionStorage.getItem(STORAGE_KEY) || "";
    if (saved) {
      setAccessKey(saved);
      setAccessKeyInput(saved);
      setIsUnlocked(true);
    }
  }, []);

  const parsedEvidenceCount = useMemo(() => {
    try {
      const parsed = JSON.parse(evidenceText);
      return Array.isArray(parsed) ? parsed.length : 0;
    } catch {
      return 0;
    }
  }, [evidenceText]);

  const loadPassSample = () => {
    setPrompt("What is revenue recognition policy?");
    setEvidenceText(JSON.stringify(passSampleEvidence, null, 2));
  };

  const loadRefuseSample = () => {
    setPrompt("Test insufficient evidence");
    setEvidenceText(JSON.stringify(refuseSampleEvidence, null, 2));
  };

  const unlockConsole = () => {
    const key = accessKeyInput.trim();
    if (!key) {
      setError("Access key is required.");
      return;
    }
    setError("");
    setAccessKey(key);
    setIsUnlocked(true);
    window.sessionStorage.setItem(STORAGE_KEY, key);
  };

  const lockConsole = () => {
    setAccessKey("");
    setAccessKeyInput("");
    setIsUnlocked(false);
    setAudit(null);
    setError("");
    window.sessionStorage.removeItem(STORAGE_KEY);
  };

  const runAudit = async () => {
    setError("");
    setAudit(null);

    if (!isUnlocked || !accessKey) {
      setError("Unlock the internal console first.");
      return;
    }

    let evidence;
    try {
      evidence = JSON.parse(evidenceText);
      if (!Array.isArray(evidence)) {
        throw new Error("Evidence must be a JSON array.");
      }
    } catch (parseError) {
      setError(parseError instanceof Error ? parseError.message : "Invalid evidence JSON.");
      return;
    }

    const payload = {
      module: moduleName.trim(),
      prompt: prompt.trim(),
      evidence,
      access_key: accessKey,
    };

    if (!payload.module || !payload.prompt) {
      setError("Module and prompt are required.");
      return;
    }

    if (requestId.trim()) {
      payload.request_id = requestId.trim();
    }

    setIsRunning(true);
    try {
      const result = await base44.functions.invoke("runGovernanceAudit", payload);
      const data = result?.data ?? result;
      if (!data?.decision) {
        throw new Error("Unexpected function response.");
      }
      setAudit(data);
    } catch (invokeError) {
      setError(invokeError instanceof Error ? invokeError.message : "Function invoke failed.");
    } finally {
      setIsRunning(false);
    }
  };

  if (!isUnlocked) {
    return (
      <div className="min-h-screen bg-slate-100">
        <div className="max-w-xl mx-auto px-6 py-16">
          <section className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
            <div className="flex items-center gap-2 text-slate-900">
              <Lock className="w-5 h-5" />
              <h1 className="text-xl font-semibold">Internal Console Locked</h1>
            </div>
            <p className="text-sm text-slate-600">
              Enter the internal access key. Requests are also enforced server-side.
            </p>
            <Input
              type="password"
              value={accessKeyInput}
              onChange={(event) => setAccessKeyInput(event.target.value)}
              placeholder="Internal access key"
            />
            <div className="flex flex-wrap gap-2">
              <Button type="button" onClick={unlockConsole}>
                Unlock
              </Button>
              <Button variant="outline" asChild>
                <a href="/">Back To Public</a>
              </Button>
            </div>
            {error ? (
              <div className="rounded border border-red-200 bg-red-50 p-2 text-sm text-red-700">
                {error}
              </div>
            ) : null}
          </section>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-slate-100">
      <div className="max-w-4xl mx-auto px-6 py-10 space-y-6">
        <header className="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
          <div className="flex items-center justify-between gap-3">
            <div>
              <h1 className="text-2xl font-semibold text-slate-900">Internal Governance Console</h1>
              <p className="text-sm text-slate-600 mt-1">
                Internal route for live `runGovernanceAudit` testing.
              </p>
            </div>
            <div className="flex gap-2">
              <Button variant="outline" onClick={lockConsole}>
                <Unlock className="w-4 h-4 mr-2" />
                Lock
              </Button>
              <Button variant="outline" asChild>
                <a href="/">Back To Public</a>
              </Button>
            </div>
          </div>
        </header>

        <section className="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            <Input
              value={moduleName}
              onChange={(event) => setModuleName(event.target.value)}
              placeholder="Module"
            />
            <Input
              value={requestId}
              onChange={(event) => setRequestId(event.target.value)}
              placeholder="Optional request_id"
            />
          </div>

          <Input
            value={prompt}
            onChange={(event) => setPrompt(event.target.value)}
            placeholder="Prompt"
          />

          <div>
            <label className="text-sm text-slate-600 block mb-2">
              Evidence JSON array ({parsedEvidenceCount} items detected)
            </label>
            <textarea
              value={evidenceText}
              onChange={(event) => setEvidenceText(event.target.value)}
              className="w-full min-h-48 rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-300"
            />
          </div>

          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="outline" onClick={loadPassSample}>
              Load PASS sample
            </Button>
            <Button type="button" variant="outline" onClick={loadRefuseSample}>
              Load REFUSE sample
            </Button>
            <Button type="button" onClick={runAudit} disabled={isRunning}>
              {isRunning ? "Running..." : "Run Governance Audit"}
            </Button>
          </div>
        </section>

        {error ? (
          <section className="rounded-xl border border-red-200 bg-red-50 p-4 text-red-700 text-sm">
            <div className="font-medium mb-1">Invocation error</div>
            <div>{error}</div>
          </section>
        ) : null}

        {audit ? (
          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-center gap-2 mb-3">
              {audit.decision === "PASS" ? (
                <CheckCircle2 className="w-5 h-5 text-emerald-600" />
              ) : (
                <XCircle className="w-5 h-5 text-amber-600" />
              )}
              <span className="font-semibold text-slate-900">{audit.decision}</span>
            </div>
            <div className="text-sm text-slate-700 mb-3">{audit.reason}</div>
            <pre className="text-xs bg-slate-50 border border-slate-200 rounded-lg p-3 overflow-x-auto">
{JSON.stringify(audit, null, 2)}
            </pre>
          </section>
        ) : null}
      </div>
    </div>
  );
}
