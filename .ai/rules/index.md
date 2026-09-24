# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| app/** | .ai/rules/app.md |
| ** | .ai/rules/general.md |
| database/migrations/** | .ai/rules/migrations.md |
| tests/** | .ai/rules/tests.md |

Running a command is not editing a file, so nothing above fires for it. Before
running the Postgres suite (`phpunit.postgres.xml`, about 165 seconds), the
default is **not** to: the criterion is in `.ai/rules/tests.md`.
