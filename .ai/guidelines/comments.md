# Code Comments

- Write every comment for someone who has only the code in front of them. If that reader cannot tell why a comment is there, it does not belong, whatever its size.
- Documentation is welcome, preferably as `/** */` docblocks on classes and methods: what the code does, and why it is shaped that way when that is not obvious. There is no length limit, but every sentence must earn its place.
- Never write what only makes sense with outside context: history ("was X, now Y", "the framework defaults to…"), the discussion or decision that led to the code, planning ("this cycle", "later"), or what a test proved.
- Reasoning that depends on outside context goes in the commit message or `docs/superpowers/specs/`. A link to that spec or to an issue is fine.
- Tests follow the same rules: the test name states the intent, and assertions are not narrated.
- Framework scaffolding docblocks stay as shipped.
