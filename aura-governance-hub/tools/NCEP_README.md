# NCEP v1.0

Notebook Constraint Extraction Protocol for deterministic constraint mining.

## What it does
- Extracts constraint primitives (`CP_i: condition -> consequence`) from local corpus files.
- Builds a DAG of primitive dependencies.
- Labels primitives as `LB` (load-bearing), `R` (redundant), or `D` (derived).
- Emits geometry artifacts (`invariant_region`, `boundary_conditions`, `allowed_parameter_corridors`, `forbidden_regions`).

## Run
```bash
cd "/Users/josephocasio/Documents/New project/aura-governance-hub"

python3 tools/ncep.py extract \
  --src "/ABSOLUTE/PATH/TO/NOTEBOOK_EXPORT" \
  --out "/Users/josephocasio/Documents/New project/aura-governance-hub/out/ncep_run_001" \
  --max-cp 200
```

## Outputs
- `constraint_primitives.json`
- `constraint_primitives.csv`
- `dependency_dag.json`
- `dependency_edges.csv`
- `geometry_map.json`
- `summary.json`

## Notes
- Extraction is deterministic and regex-based (no LLM in this step).
- The parser intentionally ignores narrative/theological framing.
- Quality improves when source documents are plain text with explicit conditional statements.
