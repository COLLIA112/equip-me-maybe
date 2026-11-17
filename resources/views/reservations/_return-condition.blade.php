<label for="return-condition-{{ $r->id }}" class="block text-xs font-medium">Return condition</label>
<select id="return-condition-{{ $r->id }}" name="return_condition" class="max-w-full rounded border-slate-300 text-sm" onchange="this.form.elements.return_notes.required = this.value !== 'good'">
    <option value="good">Good condition; all parts present</option>
    <option value="damaged">Damage found</option>
    <option value="missing_parts">Parts missing</option>
</select>
<label for="return-notes-{{ $r->id }}" class="block text-xs font-medium">Condition notes (required for damage or missing parts)</label>
<textarea id="return-notes-{{ $r->id }}" name="return_notes" rows="2" maxlength="2000" class="max-w-full rounded border-slate-300 text-sm"></textarea>
