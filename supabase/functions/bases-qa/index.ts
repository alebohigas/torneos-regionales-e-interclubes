/**
 * bases-qa
 * Responde preguntas de los participantes usando únicamente el texto de las Bases.
 * El modelo se llama vía Lovable AI Gateway (Responses API, siempre en streaming).
 */

import { BASES_TEXT } from './basesText.ts';

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type',
  'Access-Control-Allow-Methods': 'POST, OPTIONS',
};

const SYSTEM_PROMPT = `Eres un asistente del Comité Infantil Juvenil Zona Norte.
Respondes preguntas sobre las BASES del torneo usando EXCLUSIVAMENTE el documento incluido abajo.
Reglas:
- Responde en español, claro y breve (máximo 6 frases o una lista corta).
- Cita el número de sección o apartado cuando aplique.
- Si la respuesta no está en el documento, di: "Eso no aparece en las Bases; consulta al comité."
- No inventes fechas, montos ni categorías.

=== DOCUMENTO: BASES ===
${BASES_TEXT}
=== FIN DEL DOCUMENTO ===`;

Deno.serve(async (req) => {
  if (req.method === 'OPTIONS') {
    return new Response(null, { headers: corsHeaders });
  }

  try {
    const apiKey = Deno.env.get('LOVABLE_API_KEY');
    if (!apiKey) {
      return new Response(JSON.stringify({ error: 'Falta la configuración de IA.' }), {
        status: 500,
        headers: { ...corsHeaders, 'Content-Type': 'application/json' },
      });
    }

    const { question } = await req.json().catch(() => ({ question: '' }));
    const pregunta = typeof question === 'string' ? question.trim() : '';

    if (!pregunta) {
      return new Response(JSON.stringify({ error: 'Escribe una pregunta.' }), {
        status: 400,
        headers: { ...corsHeaders, 'Content-Type': 'application/json' },
      });
    }

    const upstream = await fetch('https://ai.gateway.lovable.dev/v1/responses', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Lovable-API-Key': apiKey,
        'X-Lovable-AIG-SDK': 'fetch',
      },
      body: JSON.stringify({
        model: 'openai/gpt-6-astra',
        instructions: SYSTEM_PROMPT,
        input: [
          {
            role: 'user',
            content: [{ type: 'input_text', text: pregunta.slice(0, 500) }],
          },
        ],
        stream: true,
        reasoning: { effort: 'low', summary: 'auto' },
        include: ['reasoning.encrypted_content'],
        store: false,
      }),
    });

    if (!upstream.ok || !upstream.body) {
      const detail = await upstream.text().catch(() => '');
      console.error('AI gateway error', upstream.status, detail);
      const message =
        upstream.status === 429
          ? 'Hay muchas preguntas en este momento, intenta de nuevo en unos segundos.'
          : upstream.status === 402
            ? 'El servicio de respuestas no tiene créditos disponibles.'
            : 'No se pudo generar la respuesta.';
      return new Response(JSON.stringify({ error: message }), {
        status: upstream.status === 429 || upstream.status === 402 ? upstream.status : 502,
        headers: { ...corsHeaders, 'Content-Type': 'application/json' },
      });
    }

    /** Se consume el stream en el servidor: la UI solo necesita el texto final. */
    const reader = upstream.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let answer = '';

    while (true) {
      const { value, done } = await reader.read();
      if (done) break;
      buffer += decoder.decode(value, { stream: true });

      const lines = buffer.split('\n');
      buffer = lines.pop() ?? '';

      for (const line of lines) {
        if (!line.startsWith('data:')) continue;
        const payload = line.slice(5).trim();
        if (!payload || payload === '[DONE]') continue;
        try {
          const event = JSON.parse(payload);
          if (event.type === 'response.output_text.delta' && typeof event.delta === 'string') {
            answer += event.delta;
          } else if (event.type === 'response.completed' && !answer) {
            answer = event.response?.output_text ?? '';
          }
        } catch {
          // Evento parcial o no JSON: se ignora.
        }
      }
    }

    return new Response(
      JSON.stringify({
        answer:
          answer.trim() ||
          'No pude formular una respuesta con las Bases. Intenta reformular la pregunta.',
      }),
      { headers: { ...corsHeaders, 'Content-Type': 'application/json' } },
    );
  } catch (error) {
    console.error('bases-qa failure', error);
    return new Response(JSON.stringify({ error: 'Ocurrió un error al responder.' }), {
      status: 500,
      headers: { ...corsHeaders, 'Content-Type': 'application/json' },
    });
  }
});
