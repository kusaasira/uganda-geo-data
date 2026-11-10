"use strict";

const byId = new Map();
let units = [];
let searchable = [];
const query = document.getElementById("query");
const level = document.getElementById("level");
const results = document.getElementById("results");
const details = document.getElementById("details");
const status = document.getElementById("status");

function createLine(tag, value, className = "") {
  const node = document.createElement(tag);
  node.textContent = value;
  if (className) node.className = className;
  return node;
}

function hierarchy(unit) {
  const chain = [];
  const seen = new Set();
  let current = unit;
  while (current) {
    if (seen.has(current.id)) throw new Error("Invalid cyclic ancestry");
    chain.unshift(current.level + ": " + current.name + " (" + current.id + ")");
    seen.add(current.id);
    if (current.parent_id && !byId.has(current.parent_id)) {
      throw new Error("Missing parent identity for " + current.id);
    }
    current = current.parent_id ? byId.get(current.parent_id) : null;
  }
  return chain;
}

function showDetails(unit) {
  details.replaceChildren();
  details.append(
    createLine("h2", unit.name),
    createLine("p", unit.level + " · ID " + unit.id, "meta"),
    createLine("h3", "Administrative hierarchy")
  );
  const list = document.createElement("ol");
  try {
    hierarchy(unit).forEach((name) => list.appendChild(createLine("li", name)));
    details.appendChild(list);
  } catch (error) {
    details.appendChild(createLine("p", "Hierarchy unavailable: " + error.message));
  }
}

function render() {
  const search = query.value.trim().toLocaleLowerCase();
  const selectedLevel = level.value;
  const filtered = searchable.filter((unit) =>
    (!selectedLevel || unit.level === selectedLevel)
    && (!search || unit.searchText.includes(search))
  );
  results.replaceChildren();
  const count = Math.min(filtered.length, 200);
  filtered.slice(0, count).forEach((unit) => {
    const item = document.createElement("li");
    const button = document.createElement("button");
    button.type = "button";
    button.append(
      createLine("strong", unit.name),
      createLine("span", unit.level + " · " + unit.id, "meta")
    );
    button.addEventListener("click", () => showDetails(unit));
    item.appendChild(button);
    results.appendChild(item);
  });
  if (filtered.length === 0) {
    results.appendChild(createLine("li", "No matching administrative units"));
  }
  status.textContent = filtered.length + " matching records · displaying " + count + " · no fuzzy identity merging";
}

async function initialize() {
  try {
    const response = await fetch("data.json", { cache: "no-store" });
    if (!response.ok) throw new Error("Dataset request returned " + response.status);
    const index = await response.json();
    if (!Array.isArray(index.units) || !Number.isSafeInteger(index.unit_count)
        || index.unit_count !== index.units.length || typeof index.snapshot_id !== "string"
        || !index.snapshot_id.trim()) throw new Error("Invalid or incomplete local dataset");
    units = index.units;
    for (const unit of units) {
      if (!unit || typeof unit.id !== "string" || typeof unit.name !== "string"
          || typeof unit.level !== "string" || byId.has(unit.id)) {
        throw new Error("Invalid or duplicate unit identity");
      }
      byId.set(unit.id, unit);
    }
    searchable = units.map((unit) => ({
      ...unit,
      searchText: (unit.id + " " + unit.name).toLocaleLowerCase()
    }));
    [...new Set(units.map((unit) => unit.level))].sort().forEach((name) => {
      const option = document.createElement("option");
      option.value = name;
      option.textContent = name;
      level.appendChild(option);
    });
    document.getElementById("source").textContent =
      "Snapshot: " + index.snapshot_id
      + " · Reference date: " + (index.reference_date || "unverified")
      + ". " + index.limitations;
    render();
  } catch (error) {
    status.textContent = "Could not load the local dataset: " + error.message;
  }
}

query.addEventListener("input", render);
level.addEventListener("change", render);
initialize();
