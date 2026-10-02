# Code Comments

- Write every comment for someone who has only the code in front of them. If that reader cannot tell why a comment is there, it does not belong, whatever its size.
- Documentation is welcome, preferably as `/** */` docblocks on classes and methods: what the code does, and why it is shaped that way when that is not obvious. There is no length limit, but every sentence must earn its place.
- Never write what only makes sense with outside context: history ("was X, now Y", "the framework defaults to…"), the discussion or decision that led to the code, planning ("this cycle", "later"), or what a test proved.
- A comment that makes a technical claim is proven before it is written, the same way the code is. Well-formed and false is worse than absent: it reads as authoritative, and it passes the review that only checks the form.
- Suspect hardest the claim that the simpler way would not work — it is the one that licenses complexity. Test it: if it holds, the comment has earned its place; if it does not, the complexity goes with it.
- Reasoning that depends on outside context goes in the commit message or `docs/superpowers/specs/`. A link to that spec or to an issue is fine.
- Tests follow the same rules: the test name states the intent, and assertions are not narrated.
- Framework scaffolding docblocks stay as shipped.
