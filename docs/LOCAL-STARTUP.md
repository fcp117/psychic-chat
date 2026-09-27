# Starting Intuition Island locally

After the initial environment, database, PHP and npm dependency setup:

```powershell
cd C:\Git\psychic-chat
.\start.bat
```

Alternatively, run `npm start` in the project directory (also works on other operating systems).

The launcher builds the frontend, then runs the website on http://127.0.0.1:8000, Reverb using the existing environment configuration, a queue worker, and the scheduler in one terminal. The scheduler handles reading settlement/disconnects and pending payment reconciliation. Keep the terminal open. Press Ctrl+C to stop; if Windows asks to terminate the batch job, answer Y. If a service exits, the other services stop too. Resolve the reported error before restarting.

The Library Guide uses the prebuilt, closed corpus in `rag/corpus.json` and sends only retrieved excerpts to Together AI. Add `TOGETHER_API_KEY` privately to `.env`, then set `ASSISTANT_ENABLED=true`. Never expose that key to frontend code or commit it. Rebuild `rag/corpus.json` from source PDFs only when the corpus changes.

Stop previously running copies first to avoid occupied ports or duplicate workers. The launcher does not install dependencies, migrate the database, change secrets, or reset data. This is a development launcher, not a production deployment service.

For live frontend editing use `npm run dev:all` instead. That runs Vite alongside the services. For normal use or tunnel testing, `npm start` uses built assets and avoids depending on a separate Vite connection.

Expose is optional and is not started automatically. When testing payment webhooks, start your configured Expose tunnel in a second terminal, and update the provider webhook URL if the public address changes. Never put your tunnel token or merchant secrets in the launcher.

The launcher preserves the PHP upload directory when starting Laravel. Restart the launcher after changing .env or PHP settings; automatic environment reloading is disabled. Application code and built frontend files still load normally.
