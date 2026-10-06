# Living Office visual memory

This directory is the repository source for 27 composition templates. `visual-template-memory.json` preserves the supplied T01–T27 contract and adds four independently composed layout instructions to every template. **VISUAL_REFERENCE_AUDIT = PENDING**: the original reference image binaries were unavailable in the implementation environment. The layout instructions were derived from the supplied JSON descriptions and have not been visually compared against the images.

`brands.json` stores the locked visual palette and named font roles for each brand. The latest user-provided brand rules supersede the older project CSS colors: Jauki uses the supplied green palette and Baloo 2, Fredoka, Poppins, and Inter; Kauiz uses the supplied blue palette and League Spartan, Archivo Black, and Plus Jakarta Sans. The two latest image files were not available in the accessible attachments, so those visual references have not been inspected. Templates contain no hex colors or brand fonts; the compiler supplies both from the selected brand memory. `VisualBrandMemory` refuses any unverified or unlocked palette.

The offline Python API in `JaukiContentBot-integration/visual_memory.py` supplies:

1. `VisualBrandMemory` to load a verified, locked brand palette.
2. `VisualTemplateRepository` to validate T01–T27 and four output modes.
3. `VisualTemplateSelector.select(analysis, history, seed)` for weighted matching across pattern, topic, pillar, visual subject, visual intent, key points, statistics, and text density; a three-post template gap; family scoring; and controlled variation.
4. `VisualPromptCompiler.compile_prompt(brand_slug, analysis, template_id, variation, output_mode)` to produce the existing `{model, prompt, stream}` Muse payload.

The module is intentionally not called by the production social worker yet. No new image provider client or image-generation call is introduced.

Typography is prompt-only in this offline compiler. The exact font families are stored as metadata in `brands.json`, while the Muse prompt uses them as style cues and states that exact font rendering requires a future deterministic overlay. Four complete offline compilation examples are in `offline-prompt-previews.md`; generating them did not invoke Muse.

Run offline tests from `JaukiContentBot-integration` with `python3 -m unittest -v test_visual_memory`.
