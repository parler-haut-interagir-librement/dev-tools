# Extension points

`phil/dev-tools` is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Change a package's exceptions | Write them in its `phil-dev.php`; see [Configuration](configuration.md) |
| Add a rule of the project's own | Pass rules to the closure in the thin file, or add them under `cs.rules`, `arkitect.rules` or `deptrac.configs` |
| Narrow the default dependencies | Set the root's `deptrac.default`; see [Dependencies](dependencies.md) |
| Run on other versions | Edit `config/matrix.json`; see [The version matrix](version-matrix.md) |
| Run a process differently | Implement `ProcessRunner` and pass it to `Phil\DevTools\Console\Application` |
| Read the workspace | Use `Workspace::load()` and `Tooling`, which the shared configurations use |
| Turn a gate off | Set `gates.<name>` to `false` in the root's `phil-dev.php`; see [Quality gates](quality-gates.md) |
| Fuzz a parser, measure a worker | Declare `fuzz.targets` or `bench.worker` in the package's `phil-dev.php`; see [Security checks](security.md) and [Benchmarks](benchmarks.md) |
| Compile the templates with the package's extensions | Name a loader under `twig.loader`; see [Quality gates](quality-gates.md#twig-templates) |
