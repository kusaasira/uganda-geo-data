"use strict";

const assert = require("node:assert/strict");
const { test } = require("node:test");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");

class Element {
  constructor(tag) {
    this.tag = tag;
    this.textContent = "";
    this.value = "";
    this.children = [];
    this.listeners = {};
  }
  append(...nodes) {
    this.children.push(...nodes);
  }
  appendChild(node) {
    this.children.push(node);
  }
  replaceChildren(...nodes) {
    this.children = [...nodes];
  }
  addEventListener(name, handler) {
    this.listeners[name] = handler;
  }
}

async function loadExplorer(index) {
  const elements = {};
  for (const key of ["query", "level", "results", "details", "status", "source"]) {
    elements[key] = new Element(key);
  }
  const document = {
    getElementById: (id) => elements[id],
    createElement: (tag) => new Element(tag)
  };
  const fetch = async () => ({
    ok: true,
    json: async () => index
  });
  const source = fs.readFileSync(path.join(__dirname, "app.js"), "utf8");
  vm.runInNewContext(source, { document, fetch });
  await new Promise((resolve) => setImmediate(resolve));
  return elements;
}

test("keeps duplicate names distinct and resets stale selected details", async () => {
  const elements = await loadExplorer({
    snapshot_id: "2024-census-fixture",
    reference_date: null,
    limitations: "Not official boundary verification.",
    unit_count: 3,
    units: [
      { id: "district:1", name: "District", level: "district", parent_id: null },
      { id: "parish:1", name: "Central", level: "parish", parent_id: "district:1" },
      { id: "parish:2", name: "Central", level: "parish", parent_id: "district:1" }
    ]
  });

  elements.query.value = "Central";
  elements.query.listeners.input();
  assert.equal(elements.results.children.length, 2);
  assert.match(elements.status.textContent, /^2 matching records/);

  const button = elements.results.children[0].children[0];
  button.listeners.click();
  assert.equal(elements.details.children[0].textContent, "Central");

  elements.query.value = "nonexistent";
  elements.query.listeners.input();
  assert.equal(elements.results.children.length, 1);
  assert.equal(elements.results.children[0].textContent, "No matching administrative units");
  assert.match(elements.details.children[0].textContent, /^Select a matching location/);
  assert.match(elements.source.textContent, /Reference date: unverified/);
});

test("renders untrusted locality names as text rather than HTML", async () => {
  const evilName = "<img src=x onerror=alert(1)>";
  const elements = await loadExplorer({
    snapshot_id: "fixture",
    reference_date: "2024-05-01",
    limitations: "Fixture.",
    unit_count: 1,
    units: [{ id: "city:1", name: evilName, level: "city", parent_id: null }]
  });

  const button = elements.results.children[0].children[0];
  assert.equal(button.children[0].textContent, evilName);
  button.listeners.click();
  assert.equal(elements.details.children[0].textContent, evilName);
  assert.match(elements.source.textContent, /Reference date: 2024-05-01/);
});
