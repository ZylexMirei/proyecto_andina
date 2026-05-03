import { Card, CardContent } from "@/components/ui/card";
import { AlertCircle } from "lucide-react";

export default function NotFound() {
  return (
    <div className="min-h-screen w-full flex items-center justify-center bg-[#0a0f1e] px-4">
      <Card className="w-full max-w-md mx-4 border-slate-800 bg-slate-900/80 text-slate-100">
        <CardContent className="pt-6">
          <div className="flex mb-4 gap-3 items-start">
            <AlertCircle className="h-8 w-8 text-amber-400 shrink-0" />
            <div>
              <h1 className="text-xl font-semibold text-white">Página no encontrada</h1>
              <p className="text-xs text-slate-500 mt-1">Código 404</p>
            </div>
          </div>

          <p className="mt-2 text-sm text-slate-400 leading-relaxed">
            La ruta solicitada no existe en esta aplicación. Vuelve al inicio de sesión o al dashboard
            desde el menú lateral del ERP.
          </p>
        </CardContent>
      </Card>
    </div>
  );
}
