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
  const installments = state.srcList[selected] ? state.srcList[selected].installments 
  : [];

  function setInstallments(newList, status) {
    // Update sources list
    let newSources = { ...state };
    newSources.srcList[selected].installments = newList;

    // Update all common plans that have the same reference 
    if(status && status.reference) {
      newSources.srcList.map((source, srcIndex) => {
        source.installments.map((installment, instIndex) => {
          if(!installment.advanced && installment.reference === status.reference)
            newSources.srcList[srcIndex].installments[instIndex].active = status.active;
        })
      })
    }

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
