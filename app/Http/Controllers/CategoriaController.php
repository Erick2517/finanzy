<?php
namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoriaController extends Controller {
    public function index(Request $request) {
        $categorias = Categoria::query()->where('user_id', $request->user()->id)
            ->when($request->query('q'), fn ($q, $v) => $q->where('nombre', 'like', '%'.$v.'%'))
            ->when(in_array($request->query('tipo'), ['ingreso', 'gasto']), fn ($q) => $q->where('tipo', $request->query('tipo')))
            ->withCount('movimientos')->orderBy('tipo')->orderBy('nombre')->paginate(12)->withQueryString();
        return view('categorias.index', compact('categorias'));
    }
    public function store(Request $request) {
        $data = $this->validated($request);
        Categoria::create($data + ['user_id' => $request->user()->id]);
        return redirect()->route('categorias.index')->with('success', 'Categoría creada correctamente.');
    }
    public function update(Request $request, Categoria $categoria) {
        abort_unless($categoria->user_id === $request->user()->id, 403);
        $categoria->update($this->validated($request, $categoria));
        return redirect()->route('categorias.index')->with('success', 'Categoría actualizada correctamente.');
    }
    public function destroy(Request $request, Categoria $categoria) {
        abort_unless($categoria->user_id === $request->user()->id, 403);
        if ($categoria->movimientos()->exists()) return back()->with('error', 'No se puede eliminar una categoría con movimientos asociados.');
        $categoria->delete();
        return back()->with('success', 'Categoría eliminada.');
    }
    private function validated(Request $request, ?Categoria $categoria = null): array {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('categorias', 'nombre')->where('user_id', $request->user()->id)->where('tipo', $request->input('tipo'))->ignore($categoria?->id)],
            'tipo' => ['required', Rule::in(['ingreso', 'gasto'])],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
