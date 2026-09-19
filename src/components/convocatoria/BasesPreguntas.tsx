/**
 * BasesPreguntas
 * Permite a los participantes preguntar sobre las Bases; la respuesta se genera
 * en el backend (edge function `bases-qa`) con el texto oficial del documento.
 */

import { useState } from 'react';
import { HelpCircle, Loader2, Send } from 'lucide-react';
import { supabase } from '@/integrations/supabase/client';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

const SUGERENCIAS = [
  '¿Qué categorías existen y por qué edad?',
  '¿Cómo se desempata en caso de igualdad?',
  '¿Cuál es el costo de inscripción?',
];

const BasesPreguntas = () => {
  const [question, setQuestion] = useState('');
  const [answer, setAnswer] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);

  const askQuestion = async (text: string) => {
    const pregunta = text.trim();
    if (!pregunta || isLoading) return;

    setIsLoading(true);
    setError(null);
    setAnswer(null);

    try {
      const { data, error: fnError } = await supabase.functions.invoke('bases-qa', {
        body: { question: pregunta },
      });

      if (fnError) throw fnError;

      if (data?.error) {
        setError(data.error as string);
        return;
      }

      setAnswer((data?.answer as string) ?? null);
    } catch {
      setError('No se pudo obtener la respuesta. Intenta de nuevo en unos momentos.');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="mx-auto max-w-3xl rounded-lg border border-border bg-card p-6 shadow-sm">
      <div className="mb-4 flex items-center gap-2">
        <HelpCircle className="h-5 w-5 text-primary" />
        <h3 className="text-lg font-bold uppercase tracking-wide text-foreground">
          Preguntas sobre las Bases
        </h3>
      </div>
      <p className="mb-4 text-sm text-muted-foreground">
        Escribe tu duda y se responderá con la información del documento oficial de Bases.
      </p>

      <form
        onSubmit={(event) => {
          event.preventDefault();
          void askQuestion(question);
        }}
        className="space-y-3"
      >
        <Textarea
          value={question}
          onChange={(event) => setQuestion(event.target.value)}
          placeholder="Ejemplo: ¿A qué categoría pertenece un jugador de 11 años?"
          rows={3}
          maxLength={500}
        />
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex flex-wrap gap-2">
            {SUGERENCIAS.map((sugerencia) => (
              <button
                key={sugerencia}
                type="button"
                disabled={isLoading}
                onClick={() => {
                  setQuestion(sugerencia);
                  void askQuestion(sugerencia);
                }}
                className="rounded-full bg-primary/10 px-3 py-1 text-xs text-primary transition-colors hover:bg-primary/20 disabled:opacity-50"
              >
                {sugerencia}
              </button>
            ))}
          </div>
          <Button type="submit" disabled={isLoading || !question.trim()} className="gap-2">
            {isLoading ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <Send className="h-4 w-4" />
            )}
            {isLoading ? 'Consultando…' : 'Preguntar'}
          </Button>
        </div>
      </form>

      {error && (
        <p className="mt-4 rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
          {error}
        </p>
      )}

      {answer && (
        <div className="mt-4 rounded-md border border-border bg-background p-4">
          <p className="whitespace-pre-wrap text-sm leading-relaxed text-foreground">{answer}</p>
          <p className="mt-3 text-xs text-muted-foreground">
            Respuesta generada a partir de las Bases. Ante cualquier duda, el documento oficial
            tiene validez.
          </p>
        </div>
      )}
    </div>
  );
};

export default BasesPreguntas;
