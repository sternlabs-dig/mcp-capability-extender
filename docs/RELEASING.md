# Release procedure

## Before WordPress.org approval

Work on GitHub, run checks, build the installable ZIP and test on staging. Existing sites can receive the ZIP through a manual WordPress replacement upload. GitHub releases alone do not create WordPress updates for this build.

There is intentionally no SVN deployment workflow yet. Do not add WordPress passwords, GitHub tokens or Google keys to source files or workflow YAML.

## After approval

1. Obtain the approved WordPress.org slug and SVN repository URL.
2. Confirm text-domain/folder migration and test upgrading an existing 0.3.0 installation without duplicate activation or lost settings.
3. Create a dedicated WordPress.org SVN deployment password and store it, with the committer username, in protected GitHub Actions secrets. Never paste it into chat or commit it.
4. Add a versioned release workflow using the approved slug, a pinned deployment action or reviewed SVN commands, and a GitHub environment with appropriate protection.
5. Trigger publishing only for deliberate version tags/releases, not every development push. Validate the tag, header, constant and readme version agree; exclude development files.
6. Deploy production files to SVN trunk and the matching version tag. WordPress.org serves the resulting ZIP and dashboard update metadata.
7. Check the published package and test the installed-site update path. Users choose Update now or WordPress auto-updates according to their site settings.

Confirm any selected deployment action's current documentation before enabling it. Repository secrets, SVN access and directory approval cannot be supplied by a plugin ZIP.
