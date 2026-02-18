import { createClientFromRequest } from "npm:@base44/sdk";

type Decision = "PASS" | "REFUSE_UNCERTAIN" | "VETO_POLICY";

type EvidenceItem = {
  id?: string;
  text?: string;
  source_url?: string;
  confidence?: number;
};

type Payload = {
  request_id?: string;
  module: string;
  prompt: string;
  evidence: EvidenceItem[];
  access_key?: string;
};

type EquationInputs = {
  evidenceCount: number;
  uniqueSources: number;
  avgConfidence: number;
  minEvidenceCount: number;
};

type EquationConfig = {
  alphaEvidence: number;
  betaSources: number;
  gammaConfidence: number;
};

type EquationResult = {
  score: number;
  metrics: {
    evidenceNorm: number;
    sourceNorm: number;
    confidenceNorm: number;
  };
};

const INTERNAL_ACCESS_ENABLED = true;
const DEFAULT_INTERNAL_ACCESS_KEY_HASH =
  "208661d2fc7a6eae6bcccaaca26978577d9fd192f9b06b0f0e20bdb0b4159dfc";
const INTERNAL_ACCESS_KEY_HASH =
  normalizeString(Deno.env.get("INTERNAL_ACCESS_KEY_HASH")) || DEFAULT_INTERNAL_ACCESS_KEY_HASH;

function clampNumber(value: unknown, min: number, max: number, fallback: number): number {
  const n = typeof value === "number" ? value : Number(value);
  if (!Number.isFinite(n)) return fallback;
  return Math.min(max, Math.max(min, n));
}

function normalizeString(value: unknown): string {
  return typeof value === "string" ? value.trim() : "";
}

async function sha256Hex(value: string): Promise<string> {
  const bytes = new TextEncoder().encode(value);
  const digest = await crypto.subtle.digest("SHA-256", bytes);
  return Array.from(new Uint8Array(digest))
    .map((b) => b.toString(16).padStart(2, "0"))
    .join("");
}

function uniqNonEmpty(values: string[]): string[] {
  const out: string[] = [];
  const seen = new Set<string>();
  for (const v of values) {
    const s = v.trim();
    if (!s) continue;
    if (seen.has(s)) continue;
    seen.add(s);
    out.push(s);
  }
  return out;
}

function evaluateEquation(inputs: EquationInputs, config: EquationConfig): EquationResult {
  const minEvidence = Math.max(1, Math.floor(inputs.minEvidenceCount));

  const evidenceNorm = Math.min(1, Math.max(0, inputs.evidenceCount / minEvidence));
  const sourceNorm = Math.min(1, Math.max(0, inputs.uniqueSources / minEvidence));
  const confidenceNorm = Math.min(1, Math.max(0, inputs.avgConfidence));

  const alpha = Math.max(0, config.alphaEvidence);
  const beta = Math.max(0, config.betaSources);
  const gamma = Math.max(0, config.gammaConfidence);
  const weightTotal = alpha + beta + gamma || 1;

  const weighted = alpha * evidenceNorm + beta * sourceNorm + gamma * confidenceNorm;
  const score = Math.min(1, Math.max(0, weighted / weightTotal));

  return {
    score,
    metrics: {
      evidenceNorm,
      sourceNorm,
      confidenceNorm,
    },
  };
}

Deno.serve(async (req) => {
  try {
    const base44 = createClientFromRequest(req);
    const db = base44.asServiceRole.entities;

    const payload = (await req.json()) as Partial<Payload>;
    const module = normalizeString(payload.module);
    const prompt = normalizeString(payload.prompt);
    const evidenceRaw = Array.isArray(payload.evidence) ? payload.evidence : null;
    const requestId = normalizeString(payload.request_id);
    const accessKey = normalizeString(payload.access_key);

    if (!module) return Response.json({ error: "Missing or invalid 'module'" }, { status: 400 });
    if (!prompt) return Response.json({ error: "Missing or invalid 'prompt'" }, { status: 400 });
    if (!evidenceRaw) return Response.json({ error: "'evidence' must be an array" }, { status: 400 });

    const evidence: EvidenceItem[] = [];
    for (const item of evidenceRaw) {
      if (!item || typeof item !== "object") {
        return Response.json({ error: "Each evidence item must be an object." }, { status: 400 });
      }
      const row = item as EvidenceItem;
      const confidenceValue = row.confidence;
      const normalizedConfidence =
        confidenceValue === undefined
          ? undefined
          : clampNumber(confidenceValue, 0, 1, 0);
      evidence.push({
        id: normalizeString(row.id),
        text: normalizeString(row.text),
        source_url: normalizeString(row.source_url),
        confidence: normalizedConfidence,
      });
    }

    // Load or seed active config.
    let config = null;
    const configs = await db.GovernanceConfig.filter({ key: "active" });
    if (Array.isArray(configs) && configs.length > 0) {
      config = configs[0];
    } else {
      config = await db.GovernanceConfig.create({
        key: "active",
        version: "2026.03.04",
        min_rank: 0.75,
        equation_min_score: 0.75,
        equation_version: "ocasio-v1",
        eq_alpha_evidence: 0.5,
        eq_beta_sources: 0.5,
        eq_gamma_confidence: 0.0,
        min_evidence_count: 2,
        blocked_terms: [],
        enabled: true,
      });
    }

    const enabled = Boolean(config.enabled ?? true);
    const minEvidence = Math.max(1, Math.floor(clampNumber(config.min_evidence_count, 1, 1000, 2)));
    const blockedTerms: string[] = Array.isArray(config.blocked_terms) ? config.blocked_terms : [];
    const version = normalizeString(config.version) || "2026.03.04";
    const equationVersion = normalizeString(config.equation_version) || "ocasio-v1";
    const minScore = clampNumber(config.equation_min_score ?? config.min_rank, 0, 1, 0.75);
    const alphaEvidence = clampNumber(config.eq_alpha_evidence, 0, 10, 0.5);
    const betaSources = clampNumber(config.eq_beta_sources, 0, 10, 0.5);
    const gammaConfidence = clampNumber(config.eq_gamma_confidence, 0, 10, 0.0);

    if (INTERNAL_ACCESS_ENABLED) {
      if (!INTERNAL_ACCESS_KEY_HASH) {
        return Response.json(
          { error: "Internal access is enabled but key hash is not configured." },
          { status: 503 }
        );
      }
      if (!accessKey) {
        return Response.json({ error: "Missing access key." }, { status: 401 });
      }
      const requestKeyHash = await sha256Hex(accessKey);
      if (requestKeyHash !== INTERNAL_ACCESS_KEY_HASH) {
        return Response.json({ error: "Invalid access key." }, { status: 403 });
      }
    }

    const evidenceCount = evidence.length;
    const sourceRefs = uniqNonEmpty(evidence.map((e) => normalizeString(e.source_url)));
    const uniqueSources = sourceRefs.length;

    const confidenceValues = evidence
      .map((e) => e.confidence)
      .filter((v): v is number => typeof v === "number");
    const avgConfidence = confidenceValues.length
      ? confidenceValues.reduce((sum, v) => sum + v, 0) / confidenceValues.length
      : 0;

    const equation = evaluateEquation(
      {
        evidenceCount,
        uniqueSources,
        avgConfidence,
        minEvidenceCount: minEvidence,
      },
      {
        alphaEvidence,
        betaSources,
        gammaConfidence,
      }
    );

    // Keep rank_score for compatibility with existing UI and consumers.
    const rankScore = equation.score;
    const equationScore = equation.score;

    const promptLower = prompt.toLowerCase();
    const hasBlockedTerm = blockedTerms.some((t) => {
      const term = normalizeString(t).toLowerCase();
      return term ? promptLower.includes(term) : false;
    });

    let decision: Decision;
    let reason: string;

    if (!enabled) {
      decision = "VETO_POLICY";
      reason = "Governance disabled by policy";
    } else if (hasBlockedTerm) {
      decision = "VETO_POLICY";
      reason = "Blocked by policy term match";
    } else if (evidenceCount < minEvidence) {
      decision = "REFUSE_UNCERTAIN";
      reason = "Insufficient grounded evidence for reliable inference";
    } else if (equationScore < minScore) {
      decision = "REFUSE_UNCERTAIN";
      reason = "Equation score below configured admissibility threshold";
    } else {
      decision = "PASS";
      reason = "Sufficient grounded evidence under current policy";
    }

    const traceId = requestId ? `trace-${requestId}` : `trace-${crypto.randomUUID()}`;
    const now = new Date().toISOString();

    // Idempotency: if request_id is provided and we already have an event, return it.
    if (requestId) {
      const existing = await db.AuditEvent.filter({ trace_id: traceId });
      if (Array.isArray(existing) && existing.length > 0) {
        const e = existing[0];
        return Response.json({
          decision: e.decision,
          reason: e.reason,
          trace_id: e.trace_id,
          source_refs: e.source_refs ?? [],
          version: e.version,
          rank_score: e.rank_score ?? e.equation_score,
          equation_score: e.equation_score ?? e.rank_score,
          equation_version: e.equation_version ?? equationVersion,
          evidence_count: e.evidence_count,
        });
      }
    }

    await db.AuditEvent.create({
      trace_id: traceId,
      module,
      request_id: requestId,
      decision,
      reason,
      rank_score: Number(rankScore.toFixed(4)),
      equation_score: Number(equationScore.toFixed(4)),
      equation_version: equationVersion,
      evidence_count: evidenceCount,
      source_refs: sourceRefs,
      version,
      created_at: now,
    });

    return Response.json({
      decision,
      reason,
      trace_id: traceId,
      source_refs: sourceRefs,
      version,
      rank_score: Number(rankScore.toFixed(4)),
      equation_score: Number(equationScore.toFixed(4)),
      equation_version: equationVersion,
      equation_metrics: equation.metrics,
      evidence_count: evidenceCount,
    });
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    return Response.json({ error: message }, { status: 500 });
  }
});
