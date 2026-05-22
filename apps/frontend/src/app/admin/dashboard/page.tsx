const KPI_CARDS = [
  { label: 'Marchands', value: '—' },
  { label: 'Supérettes', value: '—' },
  { label: 'Produits référentiel', value: '—' },
  { label: "Commandes aujourd'hui", value: '—' },
];

export default function AdminDashboard() {
  return (
    <div>
      <h1 className="text-h1 font-black">Tableau de bord</h1>
      <p className="mt-1 text-muted">Bienvenue dans le backoffice Kadhia.</p>

      <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {KPI_CARDS.map((kpi) => (
          <div key={kpi.label} className="rounded-xl bg-card p-5 shadow-card">
            <span className="text-sm text-muted">{kpi.label}</span>
            <strong className="mt-1 block text-h2 font-black">{kpi.value}</strong>
          </div>
        ))}
      </div>
    </div>
  );
}
