# Living Office visual memory

This directory is the repository source for 27 composition templates. `visual-template-memory.json` preserves the supplied T01–T27 contract and adds four independently composed layout instructions to every template. The three reference image files were not present in the received attachments, so the layout instructions were derived from the supplied JSON descriptions and cannot yet be visually audited against the images.

`brands.json` is authoritative for visual palette selection. Jauki colors and typography were audited against the Jauki Tugas project's `resources/views/material/dash.blade.php` CSS variables. The Kauiz project source was not available: the local `kauiz` item is a macOS alias and `kauiz.zip` only contains that alias. Kauiz therefore has an empty, unverified palette. `VisualBrandMemory` refuses to compile its prompt until its actual source tokens are added and marked verified. Do not fill this from the reference images or the portfolio screenshot.

The offline Python API in `JaukiContentBot-integration/visual_memory.py` supplies:

1. `VisualBrandMemory` to load a verified, locked brand palette.
2. `VisualTemplateRepository` to validate T01–T27 and four output modes.
3. `VisualTemplateSelector.select(analysis, history, seed)` for weighted content matching, recent template avoidance, family scoring, and controlled variation.
4. `VisualPromptCompiler.compile_prompt(brand_slug, analysis, template_id, variation, output_mode)` to produce the existing `{model, prompt, stream}` Muse payload.

The module is intentionally not called by the production social worker yet. No new image provider client or image-generation call is introduced.

Run offline tests from `JaukiContentBot-integration` with `python3 -m unittest -v test_visual_memory`.
