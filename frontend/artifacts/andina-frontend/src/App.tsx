import { Switch, Route, Router as WouterRouter } from "wouter";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { Toaster } from "@/components/ui/toaster";
import { TooltipProvider } from "@/components/ui/tooltip";
import NotFound from "@/pages/not-found";

const queryClient = new QueryClient();

function Home() {
  return (
    <div className="min-h-screen w-full flex items-center justify-center bg-[#0a0f1e] text-slate-200 px-4">
      <div className="text-center max-w-md">
        <p className="text-xs uppercase tracking-[0.2em] text-slate-500 mb-2">Distribuidora Andina SRL</p>
        <h1 className="text-xl font-semibold text-white">Sistema de gestión integral</h1>
        <p className="mt-3 text-sm text-slate-400 leading-relaxed">
          Las pantallas de trabajo (inicio de sesión, dashboard y módulos) se sirven como vistas HTML
          del proyecto. Esta capa React cubre utilidades y rutas internas del bundle.
        </p>
      </div>
    </div>
  );
}

function Router() {
  return (
    <Switch>
      <Route path="/" component={Home} />
      <Route component={NotFound} />
    </Switch>
  );
}

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <TooltipProvider>
        <WouterRouter base={import.meta.env.BASE_URL.replace(/\/$/, "")}>
          <Router />
        </WouterRouter>
        <Toaster />
      </TooltipProvider>
    </QueryClientProvider>
  );
}

export default App;
