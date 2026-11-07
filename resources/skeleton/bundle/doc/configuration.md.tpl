# Configuration

The bundle's configuration lives under `{{alias}}`:

```yaml
# config/packages/{{alias}}.yaml
{{alias}}:
    enabled: true
```

| Option | Default | What it does |
|---|---|---|
| `enabled` | `true` | Registers the bundle's services; `false` registers none. |
