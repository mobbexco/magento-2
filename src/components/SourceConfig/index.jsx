import { useState } from 'react';
import Installments from './Installments';
import Sources from './Sources';
import './assets/styles.css';
import './assets/toggle.css';
import Installment from './Installment';

export default function SourceConfig({ srcList, form }) {
  const [state, setState] = useState({
    srcList,
    selected: 0,
  });
  const selected = state.selected;
  const sources = state.srcList;
  const installments =
    state.srcList[selected] && state.srcList[selected].installments.enabled
      ? state.srcList[selected].installments.list
      : [];

  function setInstallments(newList) {
    let newSources = { ...state };
    newSources.srcList[selected].installments.list = newList;
    setState(newSources);
  }

  function setSelected(newSelected) {
    setState({ ...state, selected: newSelected });
  }

  function setSrcList(newList) {
    setState({ srcList: newList });
  }

  return (
    <div className="mbbx-sort-container">
      <input type="hidden" name="mbbx_sources" value={JSON.stringify(sources)} data-form-part={form}></input>
      <Sources
        list={sources}
        setList={setSrcList}
        selected={selected}
        setSelected={setSelected}
      />
      <Installments list={installments} setList={setInstallments} />
    </div>
  );
}
