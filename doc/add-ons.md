# The add-on convention

A package extends another through the extension points the other documents, never by reaching into its internals.

- **A Symfony bundle registers tagged services.** The bundle that offers an extension point declares a tag — or an
  interface it autoconfigures — and collects every service so tagged; the add-on registers its services with that tag.
  Neither knows the other's classes beyond the interface of the extension point.
- **A library takes its extension points as arguments**: an interface it receives, a list of implementations, a
  callable. It never looks them up.
- **Each package documents its own extension points** in `doc/extension-points.md`: what can be replaced, what can be
  added, under which tag or interface. The skeleton writes the file, and `check-docs` requires it.
- **Extension points are stable only after 1.0.** Before its 1.0 release a package may change them in a minor version;
  its CHANGELOG says how. From 1.0, they follow semantic versioning.
- **An optional integration is a suggestion.** The bundle requires the integrated package in development only, suggests
  it, and registers what uses it only when the package is installed, as the dependency analysis of the package says.
